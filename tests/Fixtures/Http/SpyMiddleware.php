<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Records that it ran, then delegates to the next handler — proving a middleware runs before the handler. */
final class SpyMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Recorder $recorder)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->recorder->record('spy');

        return $handler->handle($request);
    }
}
