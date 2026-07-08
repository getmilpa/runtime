<?php

declare(strict_types=1);

namespace Milpa\Runtime\Http;

use Milpa\Http\HttpMethod;
use Milpa\Http\Routing\MatchStatus;
use Milpa\Http\Routing\RouteResult;
use Milpa\Runtime\Kernel;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The web entry point over a booted {@see Kernel}: matches the request against the kernel's
 * `milpa/http` route table and dispatches to the resolved controller, or answers 404/405 —
 * never a legacy `Milpa\Web` request/response pair, only `psr/http-message` types, per this
 * front's mandate to route+dispatch entirely on `milpa/http`.
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

        $resolver = new ContainerHandlerResolver($this->kernel->container());
        $handler = $resolver->resolve($handlerReference);

        return $handler->handle($request->withAttribute(RouteResult::ATTRIBUTE, $result));
    }

    private function methodNotAllowed(RouteResult $result): ResponseInterface
    {
        $allowed = implode(', ', array_map(static fn (HttpMethod $m): string => $m->value, $result->allowedMethods));

        return $this->responseFactory->createResponse(405)->withHeader('Allow', $allowed);
    }
}
