<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;

/**
 * Carries the metadata a plugin needs and takes the same constructor, but does
 * NOT implement {@see \Milpa\Interfaces\Plugin\PluginInterface}. Configuring it
 * is the authoring mistake the kernel has to name out loud.
 */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'NotAPluginAtAll',
    type: 'Service',
)]
final class NotAPluginAtAll
{
    public function __construct(private readonly DIContainerInterface $container)
    {
    }
}
