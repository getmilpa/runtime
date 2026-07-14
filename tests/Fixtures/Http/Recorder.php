<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

/**
 * A container-shared singleton the pipeline fixtures append to, so a test can read back the exact
 * order in which the route's middlewares and the handler ran.
 */
final class Recorder
{
    /** @var list<string> */
    public array $trail = [];

    public function record(string $mark): void
    {
        $this->trail[] = $mark;
    }
}
