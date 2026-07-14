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
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A minimal PSR-15 relay that composes a route's `middleware[]` in front of its resolved handler:
 * `middleware[0]` wraps `middleware[1]` wraps … wraps the `$tip` handler, so the middlewares run in
 * declaration order and the innermost call is the controller.
 *
 * Immutable and re-entrant by design: each step advances by handing the next middleware a NEW
 * pipeline pinned one index deeper, rather than mutating a shared cursor — so a middleware that
 * short-circuits (returns without calling `$handler->handle()`) simply never advances, and the same
 * pipeline instance can be handled more than once without state bleeding between runs.
 */
final class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * @param list<MiddlewareInterface> $queue the route's middlewares, in declaration order
     * @param RequestHandlerInterface   $tip   the resolved route handler, run once the queue is exhausted
     * @param int                       $index the middleware this pipeline will invoke next
     */
    public function __construct(
        private readonly array $queue,
        private readonly RequestHandlerInterface $tip,
        private readonly int $index = 0,
    ) {
    }

    /** Invokes the middleware at the current index (advancing to the next), or the tip handler when the queue is spent. */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->queue[$this->index] ?? null;
        if ($middleware === null) {
            return $this->tip->handle($request);
        }

        return $middleware->process($request, new self($this->queue, $this->tip, $this->index + 1));
    }
}
