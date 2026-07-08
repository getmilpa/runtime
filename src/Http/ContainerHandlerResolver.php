<?php

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
