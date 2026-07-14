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

namespace Milpa\Runtime\Http;

use Milpa\Http\HttpMethod;
use Milpa\Http\Routing\MatchStatus;
use Milpa\Http\Routing\RouteResult;
use Milpa\Runtime\Kernel;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The web entry point over a booted {@see Kernel}: matches the request against the kernel's
 * `milpa/http` route table, composes the matched route's per-route PSR-15 `middleware[]` in front
 * of the resolved controller, and dispatches — or answers 404/405 — never a legacy `Milpa\Web`
 * request/response pair, only `psr/http-message` types, per this front's mandate to route+dispatch
 * entirely on `milpa/http`. Resolving those middlewares from the DI container is the concrete
 * `MiddlewareResolverInterface` the host kernel always owed `milpa/http` (see {@see ContainerMiddlewareResolver}).
 *
 * Building the 404/405 responses needs a concrete PSR-7 message implementation, so this class
 * takes a PSR-17 {@see ResponseFactoryInterface} instead of hardcoding one (nyholm, guzzle, …) —
 * the host wires whichever implementation it already depends on.
 */
final class RequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    /**
     * Matches `$request` against the kernel's route table and returns the controller's
     * response, or a 404/405 built from the injected response factory.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->kernel->router()->match($request);

        return match ($result->status) {
            MatchStatus::MATCHED => $this->dispatch($result, $request),
            MatchStatus::METHOD_NOT_ALLOWED => $this->methodNotAllowed($result),
            MatchStatus::NOT_FOUND => $this->responseFactory->createResponse(404),
        };
    }

    /**
     * Resolves the matched route's handler and, when the route declares `middleware[]`, composes
     * those PSR-15 middlewares in front of it (via the container-backed {@see ContainerMiddlewareResolver}
     * and the {@see MiddlewarePipeline} relay) so they run in declaration order with the handler
     * innermost. A route with no middleware is dispatched to the resolved handler directly — the
     * exact pre-pipeline path, byte for byte.
     */
    private function dispatch(RouteResult $result, ServerRequestInterface $request): ResponseInterface
    {
        $route = $result->route;
        $handlerReference = $route?->handler;
        if ($route === null || $handlerReference === null) {
            // Unreachable for a MATCHED result under a conformant RouterInterface — a matched
            // route is always bound — but PHPStan sees Route::$handler as nullable, and a loud
            // 500 beats a null-pointer fatal if a future router implementation slips this.
            return $this->responseFactory->createResponse(500);
        }

        $container = $this->kernel->container();
        $handler = (new ContainerHandlerResolver($container))->resolve($handlerReference);
        $request = $request->withAttribute(RouteResult::ATTRIBUTE, $result);

        if ($route->middleware === []) {
            return $handler->handle($request);
        }

        $middlewareResolver = new ContainerMiddlewareResolver($container);
        $pipeline = new MiddlewarePipeline(
            array_map(
                static fn (string $middleware): MiddlewareInterface => $middlewareResolver->resolve($middleware),
                $route->middleware,
            ),
            $handler,
        );

        return $pipeline->handle($request);
    }

    private function methodNotAllowed(RouteResult $result): ResponseInterface
    {
        $allowed = implode(', ', array_map(static fn (HttpMethod $m): string => $m->value, $result->allowedMethods));

        return $this->responseFactory->createResponse(405)->withHeader('Allow', $allowed);
    }
}
