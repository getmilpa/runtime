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

namespace Milpa\Runtime\Tests\Fixtures\Stack;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;
use Milpa\Runtime\Stack\EnvVar;
use Milpa\Runtime\Stack\PortMapping;
use Milpa\Runtime\Stack\ServiceDeclaration;
use Milpa\Runtime\Stack\StackProviderInterface;

/** A plugin that needs one backing service: a message hub published on a host port. */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'HubPlugin',
    type: 'Web',
)]
final class HubPlugin implements PluginInterface, StackProviderInterface
{
    public function __construct(private readonly DIContainerInterface $container)
    {
    }

    public function boot(): void
    {
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

    /** @return list<ServiceDeclaration> */
    public function services(): array
    {
        return [
            new ServiceDeclaration(
                name: 'hub',
                image: 'example/hub:1',
                ports: [new PortMapping(container: 80, host: 3000)],
                env: [
                    new EnvVar('SERVER_NAME', value: ':80'),
                    new EnvVar('HUB_JWT_KEY', configKey: 'hub.key', secret: true),
                ],
                summary: 'Pushes shell changes to the browser.',
            ),
        ];
    }
}
