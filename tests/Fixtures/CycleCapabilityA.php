<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

/** One half of the two-capability cycle: provided by {@see CyclePluginA}, required by {@see CyclePluginB}. */
interface CycleCapabilityA
{
}
