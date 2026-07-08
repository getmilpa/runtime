<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Http;

use Milpa\Http\HttpMethod;
use Milpa\Http\Routing\HandlerReference;
use Milpa\Http\Routing\MatchStatus;
use Milpa\Http\Routing\Route;
use Milpa\Runtime\Http\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchesAnExactSegment(): void
    {
        $route = new Route('/ping', HttpMethod::GET, handler: HandlerReference::action(self::class));
        $router = new Router($route);

        $result = $router->match(new ServerRequest('GET', '/ping'));

        $this->assertSame(MatchStatus::MATCHED, $result->status);
        $this->assertSame($route, $result->route);
    }

    public function testExtractsAPlaceholderParameter(): void
    {
        $route = new Route('/posts/{id}', HttpMethod::GET, handler: HandlerReference::action(self::class));
        $router = new Router($route);

        $result = $router->match(new ServerRequest('GET', '/posts/42'));

        $this->assertTrue($result->isMatched());
        $this->assertSame('42', $result->parameter('id'));
    }

    public function testNotFoundWhenNoRouteMatchesThePath(): void
    {
        $router = new Router();

        $result = $router->match(new ServerRequest('GET', '/nope'));

        $this->assertSame(MatchStatus::NOT_FOUND, $result->status);
    }

    public function testMethodNotAllowedCarriesTheAllowedVerbs(): void
    {
        $route = new Route('/posts', [HttpMethod::GET, HttpMethod::POST], handler: HandlerReference::action(self::class));
        $router = new Router($route);

        $result = $router->match(new ServerRequest('DELETE', '/posts'));

        $this->assertSame(MatchStatus::METHOD_NOT_ALLOWED, $result->status);
        $this->assertSame([HttpMethod::GET, HttpMethod::POST], $result->allowedMethods);
    }
}
