<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;

/**
 * A plugin that requires {@see MissingCapability} — which no fixture plugin ever provides — so the
 * capability graph MUST fail pre-boot whenever this plugin is configured without a provider.
 */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'RequiringPlugin',
    type: 'Service',
    requires: [MissingCapability::class],
)]
final class RequiringPlugin implements PluginInterface
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
