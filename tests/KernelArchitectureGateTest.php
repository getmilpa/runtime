<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests;

use Milpa\Events\CapabilityResolvedEvent;
use Milpa\Eventing\EventDispatcher;
use Milpa\Exceptions\Plugin\PluginDependencyException;
use Milpa\Resolver\Events\ArchitectureResolvedEvent;
use Milpa\Resolver\Exceptions\InvalidManifestException;
use Milpa\Resolver\Report\ResolutionReport;
use Milpa\Resolver\Report\ResolutionStatus;
use Milpa\Runtime\Kernel;
use Milpa\Runtime\Tests\Fixtures\DependentPlugin;
use Milpa\Runtime\Tests\Fixtures\ProvidingPlugin;
use Milpa\Runtime\Tests\Fixtures\RequiringPlugin;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The boot-gate through milpa/resolver (runtime 0.3.1). These tests pin the NEW behaviour — the
 * resolver replaces CapabilityGraphChecker, a blocked graph throws a learnable message, and the report
 * travels on the new 'architecture.resolved' event — while the untouched {@see KernelBootTest} proves
 * the observable BC (same plugins/order/events as before). The two suites together are the BC proof.
 */
final class KernelArchitectureGateTest extends TestCase
{
    protected function setUp(): void
    {
        ProvidingPlugin::$bootCount = 0;
        DependentPlugin::$bootOrder = [];
        RequiringPlugin::$booted = false;
    }

    public function testAValidGraphBootsAndFiresArchitectureResolvedBeforeCapabilityResolved(): void
    {
        $dispatcher = new EventDispatcher(new NullLogger());
        $order = [];
        $report = null;
        $loadOrder = null;
        $dispatcher->subscribe('architecture.resolved', static function (string $e, array $payload) use (&$order, &$report): void {
            $order[] = $e;
            $report = $payload['event'];
        });
        $dispatcher->subscribe('capability.resolved', static function (string $e, array $payload) use (&$order, &$loadOrder): void {
            $order[] = $e;
            $loadOrder = $payload['event'];
        });

        // Configured out of dependency order — the resolver gate must not disturb the boot order.
        $kernel = Kernel::boot(['plugins' => [DependentPlugin::class, ProvidingPlugin::class], 'dispatcher' => $dispatcher]);

        // BC: same booted plugins, same dependency order as before the resolver landed.
        self::assertSame(['ProvidingPlugin', 'DependentPlugin'], $kernel->bootedPluginNames());

        // NEW: architecture.resolved fires, carrying the full report, strictly before capability.resolved.
        self::assertSame(['architecture.resolved', 'capability.resolved'], $order);
        self::assertInstanceOf(ArchitectureResolvedEvent::class, $report);
        self::assertInstanceOf(ResolutionReport::class, $report->report);
        self::assertNotSame(ResolutionStatus::Blocked, $report->report->status, 'a booted graph is never blocked');

        // BC: capability.resolved still carries the same CapabilityResolvedEvent with the load order.
        self::assertInstanceOf(CapabilityResolvedEvent::class, $loadOrder);
        $names = array_map(static fn (array $entry): string => (string) $entry['name'], $loadOrder->loadOrder);
        self::assertSame(['ProvidingPlugin', 'DependentPlugin'], $names);
    }

    public function testAnUnmetRequireThrowsALearnableBlockedMessageNotTheOldCheckerMessage(): void
    {
        try {
            Kernel::boot(['plugins' => [RequiringPlugin::class]]);
            self::fail('a graph with an unmet require must block boot');
        } catch (PluginDependencyException $e) {
            $message = $e->getMessage();
            // The learnable message teaches: the CODE, the why, a concrete fix, and the Academy link.
            self::assertStringContainsString('MILPA_CAPABILITY_MISSING', $message);
            self::assertStringContainsString('Fix:', $message);
            self::assertStringContainsString('Learn:', $message);
            self::assertStringContainsString('academy.milpa.lat', $message);
            // And it is NOT the old CapabilityGraphChecker message.
            self::assertStringNotContainsString('which is not available', $message);
        }

        self::assertFalse(RequiringPlugin::$booted, 'boot() must never run once the graph is blocked');
    }

    public function testAConfigHostProfileWithAnUnmetRequiredCapabilityBlocksBoot(): void
    {
        $this->expectException(PluginDependencyException::class);

        // ProvidingPlugin provides TestCapability, but the host profile demands a capability no plugin
        // provides — the host profile, not just plugin requires, drives the gate.
        Kernel::boot([
            'plugins' => [ProvidingPlugin::class],
            'hostProfile' => [
                'name' => 'strict-host',
                'version' => '1.0.0',
                'requiredCapabilities' => ['some.capability.nobody.provides'],
            ],
        ]);
    }

    public function testTheDefaultHostProfileIsPermissiveAndNamedHost(): void
    {
        $dispatcher = new EventDispatcher(new NullLogger());
        $captured = null;
        $dispatcher->subscribe('architecture.resolved', static function (string $e, array $payload) use (&$captured): void {
            $captured = $payload['event'];
        });

        $kernel = Kernel::boot(['plugins' => [ProvidingPlugin::class], 'dispatcher' => $dispatcher]);

        self::assertSame(['ProvidingPlugin'], $kernel->bootedPluginNames());
        self::assertInstanceOf(ArchitectureResolvedEvent::class, $captured);
        // The default permissive profile: named 'host', version 0.0.0 — no requiredCapabilities injected,
        // so a plugin with no unmet require resolves to a clean, valid graph.
        self::assertSame(ResolutionStatus::Valid, $captured->report->status);
        self::assertSame('host@0.0.0', $captured->report->metadata['hostProfile']);
    }

    public function testAValidEvaluatedAtIsPassedThroughAndTheGraphStillBoots(): void
    {
        $kernel = Kernel::boot([
            'plugins' => [ProvidingPlugin::class],
            'evaluatedAt' => '2026-07-11T00:00:00Z',
        ]);

        self::assertSame(['ProvidingPlugin'], $kernel->bootedPluginNames());
    }

    public function testAnInvalidEvaluatedAtIsRejectedByTheResolverNotTheRuntime(): void
    {
        // The runtime passes evaluatedAt through verbatim; the resolver's ResolutionInput is what
        // validates ISO-8601 — so a malformed clock surfaces as the resolver's own exception.
        $this->expectException(InvalidManifestException::class);

        Kernel::boot([
            'plugins' => [ProvidingPlugin::class],
            'evaluatedAt' => 'not-a-date',
        ]);
    }
}
