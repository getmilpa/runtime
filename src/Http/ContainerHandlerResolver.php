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

use Milpa\Http\Routing\HandlerReference;
use Milpa\Http\Routing\HandlerResolverInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The concrete `milpa/http` {@see HandlerResolverInterface}: pulls the controller named by a
 * {@see HandlerReference} out of a PSR-11 container and wraps it, together with the reference's
 * method name, in a {@see CallableRequestHandler}.
 */
final class ContainerHandlerResolver implements HandlerResolverInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /** Resolves a handler reference into an executable PSR-15 request handler. */
    public function resolve(HandlerReference $reference): RequestHandlerInterface
    {
        $controller = $this->container->get($reference->controller);
        if (!\is_object($controller)) {
            throw new \RuntimeException(\sprintf(
                'Controller "%s" resolved from the container is not an object (got %s).',
                $reference->controller,
                \get_debug_type($controller),
            ));
        }

        return new CallableRequestHandler($controller, $reference->method);
    }
}
