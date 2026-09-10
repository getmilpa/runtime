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

/**
 * A plugin that needs one backing service: a hub published on host port 3000, with a literal env, one
 * read from the app's config, one secret pointing at a config key no reader may ever read out, and a
 * volume.
 *
 * 🚨 IT IS THE SUPERSET OF WHAT TWO SUITES NEEDED, because the stack reader moved next to the contract
 * and its tests came with it (greenhouse decisions/0282). Widening it broke one assertion in
 * `StackProviderTest`, which pinned the secret env BY INDEX — and an index is what makes a fixture
 * unshareable. It asserts by name now, which is the property it always meant.
 */
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
    public const HOST_PORT = 3000;

    public function services(): array
    {
        return [
            new ServiceDeclaration(
                name: 'hub',
                image: 'example/hub:1',
                ports: [new PortMapping(container: 80, host: self::HOST_PORT)],
                env: [
                    new EnvVar('SERVER_NAME', value: ':80'),
                    new EnvVar('HUB_PUBLIC_URL', configKey: 'hub.public_url'),
                    new EnvVar('HUB_JWT_KEY', configKey: 'hub.key', secret: true),
                ],
                volumes: ['hub-data:/data'],
                summary: 'Pushes shell changes to the browser.',
            ),
        ];
    }
}
