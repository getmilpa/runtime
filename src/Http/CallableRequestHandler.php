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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adapts a controller instance plus a method name — the two halves of `milpa/http`'s
 * `HandlerReference` — into a live PSR-15 {@see RequestHandlerInterface}.
 *
 * The controller method is called with the (route-result-decorated) request and MUST
 * return a {@see ResponseInterface}; anything else is a controller-authoring bug, surfaced
 * loudly rather than coerced.
 */
final class CallableRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly object $controller,
        private readonly string $method,
    ) {
    }

    /** Invokes the wrapped controller method and returns its response. */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $callable = [$this->controller, $this->method];
        if (!\is_callable($callable)) {
            throw new \RuntimeException(
                \sprintf('%s::%s() is not callable as an HTTP handler.', $this->controller::class, $this->method)
            );
        }

        $response = $callable($request);
        if (!$response instanceof ResponseInterface) {
            throw new \RuntimeException(\sprintf(
                '%s::%s() must return a %s, got %s.',
                $this->controller::class,
                $this->method,
                ResponseInterface::class,
                \get_debug_type($response),
            ));
        }

        return $response;
    }
}
