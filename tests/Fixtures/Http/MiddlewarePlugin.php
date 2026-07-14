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

/** Contributes routes that exercise the per-route middleware pipeline: spy, ordered, short-circuit, plain, broken. */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'MiddlewarePlugin',
    type: 'Web',
)]
final class MiddlewarePlugin implements PluginInterface, RouteProviderInterface
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
                path: '/spy',
                methods: HttpMethod::GET,
                name: 'mw.spy',
                middleware: [SpyMiddleware::class],
                handler: new HandlerReference(RecordingController::class, 'handle'),
            ),
            new Route(
                path: '/order',
                methods: HttpMethod::GET,
                name: 'mw.order',
                middleware: [FirstMiddleware::class, SecondMiddleware::class],
                handler: new HandlerReference(RecordingController::class, 'handle'),
            ),
            new Route(
                path: '/short',
                methods: HttpMethod::GET,
                name: 'mw.short',
                middleware: [ShortCircuitMiddleware::class],
                handler: new HandlerReference(RecordingController::class, 'handle'),
            ),
            new Route(
                path: '/plain/{name}',
                methods: HttpMethod::GET,
                name: 'mw.plain',
                handler: new HandlerReference(HelloController::class, 'handle'),
            ),
            new Route(
                path: '/broken',
                methods: HttpMethod::GET,
                name: 'mw.broken',
                middleware: [NotAMiddleware::class],
                handler: new HandlerReference(RecordingController::class, 'handle'),
            ),
        ];
    }
}
