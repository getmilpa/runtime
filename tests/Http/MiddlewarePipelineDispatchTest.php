<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Http;

use Milpa\Runtime\Http\RequestHandler;
use Milpa\Runtime\Kernel;
use Milpa\Runtime\Tests\Fixtures\Http\MiddlewarePlugin;
use Milpa\Runtime\Tests\Fixtures\Http\NotAMiddleware;
use Milpa\Runtime\Tests\Fixtures\Http\Recorder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * The per-route middleware pipeline over {@see RequestHandler}: a matched route's `middleware[]`
 * are resolved from the container and composed in front of the resolved handler, running in
 * declaration order, able to short-circuit, and failing closed when a declared middleware is not
 * a real PSR-15 middleware. A route with no middleware dispatches byte-identically to the handler.
 */
final class MiddlewarePipelineDispatchTest extends TestCase
{
    public function testASpyMiddlewareRunsBeforeTheHandler(): void
    {
        $kernel = Kernel::boot(['plugins' => [MiddlewarePlugin::class]]);
        $handler = new RequestHandler($kernel, new Psr17Factory());

        $response = $handler->handle(new ServerRequest('GET', '/spy'));

        $recorder = $kernel->container()->get(Recorder::class);
        $this->assertInstanceOf(Recorder::class, $recorder);
        $this->assertSame(['spy', 'handler'], $recorder->trail);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('handled', (string) $response->getBody());
    }

    public function testAShortCircuitingMiddlewareNeverCallsTheHandler(): void
    {
        $kernel = Kernel::boot(['plugins' => [MiddlewarePlugin::class]]);
        $handler = new RequestHandler($kernel, new Psr17Factory());

        $response = $handler->handle(new ServerRequest('GET', '/short'));

        $recorder = $kernel->container()->get(Recorder::class);
        $this->assertInstanceOf(Recorder::class, $recorder);
        // The middleware ran and answered; the handler was NEVER reached.
        $this->assertSame(['short-circuit'], $recorder->trail);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('blocked', (string) $response->getBody());
    }

    public function testTwoMiddlewaresRunInDeclarationOrderThenTheHandler(): void
    {
        $kernel = Kernel::boot(['plugins' => [MiddlewarePlugin::class]]);
        $handler = new RequestHandler($kernel, new Psr17Factory());

        $handler->handle(new ServerRequest('GET', '/order'));

        $recorder = $kernel->container()->get(Recorder::class);
        $this->assertInstanceOf(Recorder::class, $recorder);
        $this->assertSame(['A', 'B', 'handler'], $recorder->trail);
    }

    public function testARouteWithNoMiddlewareDispatchesTheHandlerDirectlyByteIdentical(): void
    {
        $kernel = Kernel::boot(['plugins' => [MiddlewarePlugin::class]]);
        $handler = new RequestHandler($kernel, new Psr17Factory());

        $response = $handler->handle(new ServerRequest('GET', '/plain/milpa'));

        // Cloned expectation of the base RequestHandlerTest: no middleware => same result as before.
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('hello, milpa', (string) $response->getBody());
    }

    public function testADeclaredMiddlewareThatIsNotPsr15FailsClosed(): void
    {
        $kernel = Kernel::boot(['plugins' => [MiddlewarePlugin::class]]);
        $handler = new RequestHandler($kernel, new Psr17Factory());
        $recorder = $kernel->container()->get(Recorder::class);
        $this->assertInstanceOf(Recorder::class, $recorder);

        try {
            $handler->handle(new ServerRequest('GET', '/broken'));
            $this->fail('Expected a fail-closed exception for a non-PSR-15 route middleware, got a dispatched response.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString(NotAMiddleware::class, $e->getMessage());
        }

        // Fail-closed, not a silent passthrough: the guarded handler must never have run.
        $this->assertNotContains('handler', $recorder->trail);
    }
}
