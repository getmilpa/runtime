<?php

/**
 * This file is part of Milpa Runtime — the bootable kernel of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/runtime
 */

declare(strict_types=1);

namespace Milpa\Runtime\Events;

/**
 * Dispatched at the very end of {@see \Milpa\Runtime\Kernel::boot()}'s plugin boot sequence
 * ('kernel.booted') — regardless of whether any plugin was vetoed along the way.
 *
 * Readonly, POST, no slot — pure notification, the boot-is-complete signal for
 * audit/observability plugins.
 */
final class KernelBootedEvent
{
    /**
     * @param array<int, string> $bootedPluginNames Names of plugins whose boot() actually ran this
     *                                              boot. Excludes any vetoed via 'plugin.booting'.
     */
    public function __construct(
        public readonly array $bootedPluginNames,
    ) {
    }
}
