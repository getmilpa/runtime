<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Records the mark 'A' then delegates — the first of two ordered middlewares. */
final class FirstMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Recorder $recorder)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->recorder->record('A');

        return $handler->handle($request);
    }
}
