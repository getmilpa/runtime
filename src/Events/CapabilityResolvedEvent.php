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
 * Dispatched once the plugin capability/dependency graph for this boot is finalized
 * ('capability.resolved') — after {@see \Milpa\Services\CapabilityGraphChecker} has passed
 * and `milpa/plugin`'s `ContractResolver::getLoadOrder()` has produced the dependency-ordered
 * plugin list for this boot.
 *
 * Readonly, POST, no slot — pure notification. Fires BEFORE any plugin's boot() runs, so
 * listeners observe the finalized, dependency-ordered plugin list ahead of the boot loop.
 */
final class CapabilityResolvedEvent
{
    /**
     * @param array<int, array<string, mixed>> $loadOrder Finalized plugin metadata
     *                                                    (dependency-ordered), one entry per plugin.
     */
    public function __construct(
        public readonly array $loadOrder,
    ) {
    }
}
