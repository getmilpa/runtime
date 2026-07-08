<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

use Milpa\Attributes\PluginMetadata;
use Milpa\Http\HttpMethod;
use Milpa\Http\Routing\HandlerReference;
use Milpa\Http\Routing\Route;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;
use Milpa\Runtime\Http\RouteProviderInterface;

/** A plugin that contributes a single `GET /hello/{name}` route to the kernel's route table. */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'RoutedPlugin',
    type: 'Web',
)]
final class RoutedPlugin implements PluginInterface, RouteProviderInterface
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
            new Route(
                path: '/hello/{name}',
                methods: HttpMethod::GET,
                name: 'hello',
                handler: new HandlerReference(HelloController::class, 'handle'),
            ),
        ];
    }
}
