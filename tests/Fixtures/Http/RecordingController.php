<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** The innermost handler: records that it ran (so a short-circuit is observable) and answers 200. */
final class RecordingController
{
    public function __construct(private readonly Recorder $recorder)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->recorder->record('handler');

        return new Response(200, ['Content-Type' => 'text/plain'], 'handled');
    }
}
