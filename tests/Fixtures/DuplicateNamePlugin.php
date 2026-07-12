<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;

/**
 * A DIFFERENT class that claims {@see ProvidingPlugin}'s metadata `name`. The resolver keys its
 * graph nodes by that name, so the two records collapse into ONE `loadOrder[]` entry — the exact
 * input {@see \Milpa\Runtime\Kernel} must refuse to map silently (a skipped boot with no diagnosis).
 */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'ProvidingPlugin',
    type: 'Service',
)]
final class DuplicateNamePlugin implements PluginInterface
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
