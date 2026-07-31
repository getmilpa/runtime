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

namespace Milpa\Runtime\Boot;

/**
 * The plugin-boot phase of {@see \Milpa\Runtime\Kernel::boot()} as a swappable strategy.
 *
 * The strategy owns the WHOLE phase — instantiation, architecture gate, ordering, lifecycle
 * events and the boot loop — and reports what booted through {@see PluginBootResult}. The
 * kernel keeps everything around the phase (container/dispatcher/config/root before, router
 * and `kernel.booted` after). The default is {@see InlinePluginBootStrategy}, byte-compatible
 * with the pre-seam kernel; hosts with a richer plugin runtime (registry-driven activation,
 * caches, environment-gated tools) inject their own via `$config['pluginBoot']`.
 */
interface PluginBootStrategyInterface
{
    /**
     * Runs the kernel's whole plugin phase and reports what came out of it.
     *
     * The context carries everything a strategy may need — container, dispatcher, root, config and
     * the optional tool registry — so an implementation never reaches for global state. What it
     * returns is a value: the kernel merges the routes and commands it names, and boots nothing
     * further of its own.
     */
    public function bootPlugins(BootContext $context): PluginBootResult;
}
