<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Answers 403 WITHOUT delegating — proving a middleware can stop the handler from ever running. */
final class ShortCircuitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Recorder $recorder)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->recorder->record('short-circuit');

        return new Response(403, ['Content-Type' => 'text/plain'], 'blocked');
    }
}
