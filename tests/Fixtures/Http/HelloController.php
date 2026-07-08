<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** A trivial controller proving the kernel dispatches an HTTP request end to end, with zero database. */
final class HelloController
{
    /** Responds with a small greeting, echoing the `{name}` path parameter when present. */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $name = $request->getAttribute(\Milpa\Http\Routing\RouteResult::ATTRIBUTE)?->parameter('name', 'world') ?? 'world';

        return new Response(200, ['Content-Type' => 'text/plain'], "hello, {$name}");
    }
}
