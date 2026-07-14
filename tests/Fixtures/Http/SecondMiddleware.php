<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Records the mark 'B' then delegates — the second of two ordered middlewares. */
final class SecondMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Recorder $recorder)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->recorder->record('B');

        return $handler->handle($request);
    }
}
