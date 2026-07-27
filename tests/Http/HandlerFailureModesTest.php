<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Http;

use Milpa\Http\Routing\HandlerReference;
use Milpa\Runtime\Http\CallableRequestHandler;
use Milpa\Runtime\Http\ContainerHandlerResolver;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * What the HTTP layer does when the controller on the other end is not what the
 * route promised. Every one of these is an authoring mistake, and the contract
 * says each is surfaced loudly rather than coerced — which is only true if
 * something has actually watched it happen.
 */
final class HandlerFailureModesTest extends TestCase
{
    public function testAControllerWhoseMethodIsNotCallableIsNamedInTheError(): void
    {
        $controller = new class () {
            private function oculto(): ResponseInterface
            {
                return new Response();
            }
        };

        $handler = new CallableRequestHandler($controller, 'oculto');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('::oculto() is not callable as an HTTP handler.');

        $handler->handle(new ServerRequest('GET', '/'));
    }

    public function testAMethodThatDoesNotExistIsAlsoNamed(): void
    {
        $controller = new class () {
        };

        $handler = new CallableRequestHandler($controller, 'noExiste');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('::noExiste() is not callable as an HTTP handler.');

        $handler->handle(new ServerRequest('GET', '/'));
    }

    public function testAControllerThatReturnsSomethingElseSaysWhatItGot(): void
    {
        // Coercing this would send a 200 with a stringified array. The whole
        // point of the check is that the caller learns the type it returned.
        $controller = new class () {
            /** @return array<string, string> */
            public function index(): array
            {
                return ['no' => 'soy una respuesta'];
            }
        };

        $handler = new CallableRequestHandler($controller, 'index');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must return a ' . ResponseInterface::class . ', got array.');

        $handler->handle(new ServerRequest('GET', '/'));
    }

    public function testAControllerThatReturnsAResponseIsDispatched(): void
    {
        $controller = new class () {
            public function index(): ResponseInterface
            {
                return new Response(204);
            }
        };

        $response = (new CallableRequestHandler($controller, 'index'))->handle(new ServerRequest('GET', '/'));

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testAContainerEntryThatIsNotAnObjectIsRejectedByName(): void
    {
        // A container configured to return a factory closure's result, a
        // config array, or a string class name instead of the controller
        // itself: the reference names the id, so the error can too.
        $container = new class () implements ContainerInterface {
            public function get(string $id): mixed
            {
                return ['no' => 'soy un objeto'];
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $resolver = new ContainerHandlerResolver($container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not an object (got array)');

        $resolver->resolve(HandlerReference::action('App\\Controllers\\Home'));
    }
}
