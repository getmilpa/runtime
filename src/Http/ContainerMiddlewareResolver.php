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

use Milpa\Http\Routing\MiddlewareResolverInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * The concrete `milpa/http` {@see MiddlewareResolverInterface} its docblock has promised since day
 * one: the "concrete resolver (pulling instances from the DI container) lives in the host kernel".
 * Pulls the PSR-15 middleware named by a route's `middleware[]` `class-string` out of a PSR-11
 * container — the same container {@see ContainerHandlerResolver} draws controllers from.
 *
 * It fails CLOSED, never silent: a reference the container cannot produce, or one that resolves to
 * something that is not a {@see MiddlewareInterface}, throws instead of returning null or being
 * skipped — because a declared-but-unresolved middleware would leave the route it guards silently
 * unprotected, the one outcome a security-bearing pipeline must never reach.
 */
final class ContainerMiddlewareResolver implements MiddlewareResolverInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Resolves a per-route middleware reference into a live PSR-15 middleware, or throws a clear
     * fail-closed error when it cannot.
     */
    public function resolve(string $middleware): MiddlewareInterface
    {
        try {
            $instance = $this->container->get($middleware);
        } catch (ContainerExceptionInterface $e) {
            throw new \RuntimeException(\sprintf(
                'Route middleware "%s" could not be resolved from the container: %s. A declared '
                . 'middleware the container cannot produce would leave the route unprotected, so '
                . 'dispatch fails closed rather than skipping it.',
                $middleware,
                $e->getMessage(),
            ), 0, $e);
        }

        if (!$instance instanceof MiddlewareInterface) {
            throw new \RuntimeException(\sprintf(
                'Route middleware "%s" resolved from the container is not a %s (got %s). A declared '
                . 'middleware that is not PSR-15 cannot guard the route, so dispatch fails closed '
                . 'rather than skipping it.',
                $middleware,
                MiddlewareInterface::class,
                \get_debug_type($instance),
            ));
        }

        return $instance;
    }
}
