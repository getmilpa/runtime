<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures\Http;

/** A container-resolvable class that is NOT a PSR-15 middleware — the fail-closed resolver must reject it. */
final class NotAMiddleware
{
}
