<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

/** The other half of the two-capability cycle: provided by {@see CyclePluginB}, required by {@see CyclePluginA}. */
interface CycleCapabilityB
{
}
