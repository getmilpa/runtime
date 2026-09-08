<?php

/**
 * This file is part of Milpa Runtime — the bootable kernel of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/runtime
 */

declare(strict_types=1);

namespace Milpa\Runtime\Tests;

use Milpa\Events\InterceptionSlot;
use Milpa\Eventing\EventDispatcher;
use Milpa\Interfaces\Event\DeclaredEvents;
use Milpa\Interfaces\Event\EventDeclaration;
use Milpa\Interfaces\Event\MilpaEventDispatcherInterface;
use Milpa\Runtime\Event\RuntimeEvents;
use Milpa\Runtime\Kernel;
use Milpa\Runtime\Tests\Fixtures\ProvidingPlugin;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The falsifier of greenhouse decisions/0228 for this package: the kernel's REAL boot path is driven
 * with a spy dispatcher that records what was declared to it and what was dispatched through it, and
 * the two sets are held against each other and against the exact list this package is known to emit.
 * A dispatch nobody declared, a declaration that names a payload the dispatch does not carry, or a
 * declaration deleted or renamed, goes red here.
 */
final class TheKernelDeclaresEveryEventItDispatchesTest extends TestCase
{
    /** The exact names this package dispatches, in boot order. A new or renamed event must be added HERE too. */
    private const EXPECTED = [
        'architecture.resolved',
        'capability.resolved',
        'plugin.booting',
        'plugin.booted',
        'kernel.booted',
    ];

    protected function setUp(): void
    {
        ProvidingPlugin::$bootCount = 0;
    }

    public function testEveryNameTheBootDispatchesWasDeclaredToTheDispatcherBeforeTheFirstDispatch(): void
    {
        $spy = $this->spy();

        Kernel::boot(['plugins' => [ProvidingPlugin::class], 'dispatcher' => $spy]);

        $dispatched = array_keys($spy->payloads);
        $declared = array_map(static fn (EventDeclaration $d): string => $d->name, $spy->declared());
        self::assertNotSame([], $dispatched, 'the boot path under test must dispatch something, or the check is vacuous');
        self::assertSame([], array_values(array_diff($dispatched, $declared)), 'every dispatched name must have been declared');

        $firstDispatch = array_search('dispatch', $spy->sequence, true);
        $lastDeclare = array_search('declare', array_reverse($spy->sequence), true);
        self::assertNotFalse($firstDispatch);
        self::assertNotFalse($lastDeclare);
        self::assertGreaterThan(count($spy->sequence) - 1 - $lastDeclare, $firstDispatch, 'declare() must run before the first dispatch()');
    }

    public function testTheDeclaredSetIsExactlyTheFiveEventsThisPackageEmits(): void
    {
        $spy = $this->spy();

        Kernel::boot(['plugins' => [ProvidingPlugin::class], 'dispatcher' => $spy]);

        self::assertSame(self::EXPECTED, array_map(static fn (EventDeclaration $d): string => $d->name, $spy->declared()));
        self::assertSame(self::EXPECTED, array_keys($spy->payloads), 'the boot with one plugin dispatches each declared name exactly once, in declaration order');
        self::assertSame(self::EXPECTED, array_map(static fn (EventDeclaration $d): string => $d->name, RuntimeEvents::declarations()));
    }

    public function testEachDeclarationMatchesTheSubjectAndTheSlotItsDispatchReallyCarried(): void
    {
        $spy = $this->spy();

        Kernel::boot(['plugins' => [ProvidingPlugin::class], 'dispatcher' => $spy]);

        foreach ($spy->declared() as $declaration) {
            self::assertArrayHasKey($declaration->name, $spy->payloads, "«{$declaration->name}» is declared but this boot never dispatched it");
            $payload = $spy->payloads[$declaration->name];

            self::assertArrayHasKey($declaration->subjectKey, $payload, "«{$declaration->name}» declares subject key «{$declaration->subjectKey}» but the payload carries none");
            self::assertNotNull($declaration->subjectType);
            self::assertInstanceOf($declaration->subjectType, $payload[$declaration->subjectKey], "«{$declaration->name}» declares a subject of another class than it dispatches");

            $carriesSlot = ($payload['slot'] ?? null) instanceof InterceptionSlot;
            self::assertSame($declaration->interceptable, $carriesSlot, "«{$declaration->name}» declares interceptable={$declaration->interceptable} but the payload " . ($carriesSlot ? 'carries' : 'carries no') . ' InterceptionSlot');
            self::assertFalse($declaration->mutable, "«{$declaration->name}» carries a readonly value object; it is not mutable");
            self::assertTrue(class_exists($declaration->dispatchedBy), "«{$declaration->name}» names a dispatching class that does not exist");
        }
    }

    public function testTheFamilyDispatcherCountsNoUndeclaredDispatchAfterABoot(): void
    {
        $dispatcher = new EventDispatcher(new NullLogger());

        Kernel::boot(['plugins' => [ProvidingPlugin::class], 'dispatcher' => $dispatcher]);

        $declared = array_map(static fn (EventDeclaration $d): string => $d->name, $dispatcher->declared());
        self::assertSame(self::EXPECTED, $declared);
        self::assertSame([], array_values(array_diff($dispatcher->dispatched(), $declared)), 'milpa/events must count no dispatch this package left undeclared');
    }

    public function testControlADispatcherThatTakesNoDeclarationsBootsTheSameKernelUntold(): void
    {
        $plain = new class () implements MilpaEventDispatcherInterface {
            /** @var list<string> */
            public array $dispatched = [];

            public function dispatch(string $eventName, array $payload = [], bool $async = false): void
            {
                $this->dispatched[] = $eventName;
            }

            public function subscribe(string $eventName, callable $handler, int $priority = 0): void
            {
            }

            public function getSubscribers(string $eventName): array
            {
                return [];
            }

            public function hasSubscribers(string $eventName): bool
            {
                return false;
            }
        };
        self::assertNotInstanceOf(DeclaredEvents::class, $plain, 'the control must be a dispatcher that cannot take declarations');

        $kernel = Kernel::boot(['plugins' => [ProvidingPlugin::class], 'dispatcher' => $plain]);

        self::assertSame($plain, $kernel->dispatcher());
        self::assertSame(1, ProvidingPlugin::$bootCount);
        self::assertSame(['ProvidingPlugin'], $kernel->bootedPluginNames());
        self::assertSame(self::EXPECTED, $plain->dispatched, 'the same boot dispatches the same names, declared to nobody');
    }

    /**
     * A dispatcher that records, in order, every declare() and every dispatch() it receives, keeping the
     * payload of each dispatched name; it never runs a handler, so the boot under test sees no veto.
     *
     * @return MilpaEventDispatcherInterface&DeclaredEvents&object{payloads: array<string, array<string, mixed>>, sequence: list<string>}
     */
    private function spy(): MilpaEventDispatcherInterface
    {
        return new class () implements MilpaEventDispatcherInterface, DeclaredEvents {
            /** @var array<string, array<string, mixed>> the payload each name was first dispatched with */
            public array $payloads = [];

            /** @var list<string> 'declare' / 'dispatch' in the order the calls arrived */
            public array $sequence = [];

            /** @var list<EventDeclaration> */
            private array $declared = [];

            public function declare(EventDeclaration ...$events): void
            {
                foreach ($events as $event) {
                    $this->sequence[] = 'declare';
                    $this->declared[] = $event;
                }
            }

            public function declared(): array
            {
                return $this->declared;
            }

            public function dispatched(): array
            {
                return array_keys($this->payloads);
            }

            public function dispatch(string $eventName, array $payload = [], bool $async = false): void
            {
                $this->sequence[] = 'dispatch';
                $this->payloads[$eventName] ??= $payload;
            }

            public function subscribe(string $eventName, callable $handler, int $priority = 0): void
            {
            }

            public function getSubscribers(string $eventName): array
            {
                return [];
            }

            public function hasSubscribers(string $eventName): bool
            {
                return false;
            }
        };
    }
}
