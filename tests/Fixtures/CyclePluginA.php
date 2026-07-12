<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;

/**
 * One half of a dependency cycle: provides {@see CycleCapabilityA} but requires {@see CycleCapabilityB},
 * which only {@see CyclePluginB} provides — and that plugin requires this one right back. Configuring
 * both MUST block the boot with `MILPA_DEPENDENCY_CYCLE`: no boot order exists, nobody can go first.
 */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'CyclePluginA',
    type: 'Service',
    provides: [CycleCapabilityA::class],
    requires: [CycleCapabilityB::class],
)]
final class CyclePluginA implements PluginInterface
{
    public static bool $booted = false;

    public function __construct(private readonly DIContainerInterface $container)
    {
    }

    public function boot(): void
    {
        self::$booted = true;
    }

    public function install(): void
    {
    }

    public function uninstall(): void
    {
    }

    public function enable(): void
    {
    }

    public function disable(): void
    {
    }
}
