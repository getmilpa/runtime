<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;

/** A plugin that requires {@see TestCapability} — satisfiable only when {@see ProvidingPlugin} is also configured. */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'DependentPlugin',
    type: 'Service',
    requires: [TestCapability::class],
)]
final class DependentPlugin implements PluginInterface
{
    /** @var list<string> */
    public static array $bootOrder = [];

    public function __construct(private readonly DIContainerInterface $container)
    {
    }

    public function boot(): void
    {
        self::$bootOrder[] = self::class;
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
