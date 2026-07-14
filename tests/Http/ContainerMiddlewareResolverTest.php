<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Http;

use Milpa\Container\DIContainer;
use Milpa\Runtime\Http\ContainerMiddlewareResolver;
use Milpa\Runtime\Tests\Fixtures\Http\NotAMiddleware;
use Milpa\Runtime\Tests\Fixtures\Http\SpyMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;

/**
 * The concrete {@see \Milpa\Http\Routing\MiddlewareResolverInterface} the host kernel always
 * promised: pulls a PSR-15 middleware out of a PSR-11 container, and fails closed — never returns
 * null, never skips — when the reference is unresolvable or resolves to a non-middleware.
 */
final class ContainerMiddlewareResolverTest extends TestCase
{
    public function testItResolvesAMiddlewareFromTheContainer(): void
    {
        $resolver = new ContainerMiddlewareResolver(new DIContainer());

        $middleware = $resolver->resolve(SpyMiddleware::class);

        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
        $this->assertInstanceOf(SpyMiddleware::class, $middleware);
    }

    public function testItFailsClosedWhenTheClassIsNotAMiddleware(): void
    {
        $resolver = new ContainerMiddlewareResolver(new DIContainer());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(NotAMiddleware::class);

        $resolver->resolve(NotAMiddleware::class);
    }

    public function testItFailsClosedWhenTheReferenceCannotBeResolved(): void
    {
        $resolver = new ContainerMiddlewareResolver(new DIContainer());

        $this->expectException(\RuntimeException::class);
        // An interface cannot be autowired: the container throws, and the resolver rethrows a clear
        // fail-closed error rather than letting the route dispatch unprotected.
        $resolver->resolve(MiddlewareInterface::class);
    }
}
