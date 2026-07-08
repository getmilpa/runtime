<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;

/** A plugin that provides {@see TestCapability}, no dependencies of its own. */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'ProvidingPlugin',
    type: 'Service',
    provides: [TestCapability::class],
)]
final class ProvidingPlugin implements PluginInterface
{
    public static int $bootCount = 0;

    public function __construct(private readonly DIContainerInterface $container)
    {
    }

    public function boot(): void
    {
        self::$bootCount++;
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
