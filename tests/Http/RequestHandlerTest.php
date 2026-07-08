<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Http;

use Milpa\Runtime\Http\RequestHandler;
use Milpa\Runtime\Kernel;
use Milpa\Runtime\Tests\Fixtures\Http\RoutedPlugin;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end HTTP dispatch: {@see Kernel::boot()} with a plugin contributing a route, through
 * {@see RequestHandler}, to a real controller — zero database, zero legacy `Milpa\Web`, only
 * `milpa/http` + `psr/http-message` types on the wire.
 */
final class RequestHandlerTest extends TestCase
{
    public function testAMatchedRouteDispatchesToTheBoundControllerAndReturnsItsResponse(): void
    {
        $kernel = Kernel::boot(['plugins' => [RoutedPlugin::class]]);
        $handler = new RequestHandler($kernel, new Psr17Factory());

        $response = $handler->handle(new ServerRequest('GET', '/hello/milpa'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('hello, milpa', (string) $response->getBody());
    }

    public function testAnUnmatchedPathReturns404(): void
    {
        $kernel = Kernel::boot(['plugins' => [RoutedPlugin::class]]);
        $handler = new RequestHandler($kernel, new Psr17Factory());

        $response = $handler->handle(new ServerRequest('GET', '/does-not-exist'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testAWrongMethodOnAKnownPathReturns405WithAnAllowHeader(): void
    {
        $kernel = Kernel::boot(['plugins' => [RoutedPlugin::class]]);
        $handler = new RequestHandler($kernel, new Psr17Factory());

        $response = $handler->handle(new ServerRequest('POST', '/hello/milpa'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET', $response->getHeaderLine('Allow'));
    }

    public function testAKernelWithNoRoutedPluginsAnswersEveryRequestWith404(): void
    {
        $kernel = Kernel::boot();
        $handler = new RequestHandler($kernel, new Psr17Factory());

        $response = $handler->handle(new ServerRequest('GET', '/'));

        $this->assertSame(404, $response->getStatusCode());
    }
}
