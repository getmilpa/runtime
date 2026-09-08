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

use Milpa\Interfaces\Event\DeclaresEvents;
use Milpa\Interfaces\Event\EventDeclaration;
use Milpa\Runtime\Event\RuntimeEvents;
use PHPUnit\Framework\TestCase;

/**
 * The falsifier of greenhouse decisions/0228, second slice: the manifest is MEASURED, not described.
 *
 * A host reads `extra.milpa.events` from what Composer installed and resolves each name to declare this
 * package's events without constructing an emitter (measured on cattle: a CLI process built the emitters
 * of seven of the family's twenty-four events, evidence/0567). So the manifest string is executable
 * configuration: this reads the package's own `composer.json` from disk, resolves what it names, and holds
 * the list that class returns against the holder's own. A typo, a rename, a class that stopped being a
 * holder, or a declaration added to the holder and not reachable through the manifest, goes red here.
 */
final class TheManifestNamesTheHolderOfTheseEventsTest extends TestCase
{
    /** The holders a host must reach through this package's manifest. */
    private const HOLDERS = [RuntimeEvents::class];

    public function testTheManifestNamesExactlyTheHoldersOfThisPackage(): void
    {
        self::assertSame(self::HOLDERS, $this->manifestEvents());
    }

    public function testEveryClassTheManifestNamesExistsAndIsAHolder(): void
    {
        $named = $this->manifestEvents();
        self::assertNotSame([], $named, 'a manifest that names nothing cannot be measured');

        foreach ($named as $class) {
            self::assertTrue(class_exists($class), "the manifest names «{$class}», which no autoloader can resolve");
            self::assertTrue(is_a($class, DeclaresEvents::class, true), "the manifest names «{$class}», which is not a " . DeclaresEvents::class);
        }
    }

    public function testTheClassNamedInTheManifestDeclaresTheSameNamesTheHolderDoes(): void
    {
        $throughTheManifest = [];
        foreach ($this->manifestEvents() as $class) {
            self::assertTrue(is_a($class, DeclaresEvents::class, true));
            /** @var class-string<DeclaresEvents> $class */
            foreach ($class::declarations() as $declaration) {
                $throughTheManifest[] = $declaration->name;
            }
        }

        $throughTheHolder = array_map(
            static fn (EventDeclaration $declaration): string => $declaration->name,
            RuntimeEvents::declarations(),
        );

        self::assertNotSame([], $throughTheHolder, 'the holder must declare something, or the comparison is vacuous');
        self::assertSame($throughTheHolder, $throughTheManifest);
    }

    /**
     * The `extra.milpa.events` list of this package's own manifest, read from disk.
     *
     * @return list<string>
     */
    private function manifestEvents(): array
    {
        $path = dirname(__DIR__) . '/composer.json';
        self::assertFileExists($path);

        $raw = file_get_contents($path);
        self::assertIsString($raw);

        $manifest = json_decode($raw, true);
        self::assertIsArray($manifest, 'the package manifest must be readable JSON');
        self::assertArrayHasKey('extra', $manifest, 'the manifest declares no extra section');
        self::assertIsArray($manifest['extra']);
        self::assertArrayHasKey('milpa', $manifest['extra'], 'the manifest declares no extra.milpa section');
        self::assertIsArray($manifest['extra']['milpa']);
        self::assertArrayHasKey('events', $manifest['extra']['milpa'], 'the manifest names no event holder under extra.milpa.events');
        self::assertIsArray($manifest['extra']['milpa']['events']);

        $names = [];
        foreach ($manifest['extra']['milpa']['events'] as $class) {
            self::assertIsString($class);
            $names[] = $class;
        }

        return $names;
    }
}
