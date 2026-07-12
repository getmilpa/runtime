<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;

/**
 * The other half of the dependency cycle {@see CyclePluginA} opens: provides {@see CycleCapabilityB}
 * but requires {@see CycleCapabilityA} — each plugin requires what only the other provides, so the
 * pair can never be ordered and the architecture gate must block pre-boot.
 */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'CyclePluginB',
    type: 'Service',
    provides: [CycleCapabilityB::class],
    requires: [CycleCapabilityA::class],
)]
final class CyclePluginB implements PluginInterface
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
