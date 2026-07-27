<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

use Milpa\Attributes\PluginMetadata;
use Milpa\Http\HttpMethod;
use Milpa\Http\Routing\Route;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;
use Milpa\Runtime\Http\RouteProviderInterface;

/**
 * Contributes a route that was never bound to a handler.
 *
 * A conformant router never returns one of these as MATCHED, so this fixture
 * exists to stand where a non-conformant one would: the front's answer has to
 * be a loud 500, not a fatal on a null handler.
 */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'UnboundRoutePlugin',
    type: 'Service',
)]
final class UnboundRoutePlugin implements PluginInterface, RouteProviderInterface
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

    /** @return list<Route> */
    public function routes(): array
    {
        return [
            new Route(path: '/sin-handler', methods: HttpMethod::GET, name: 'sin_handler'),
        ];
    }
}
