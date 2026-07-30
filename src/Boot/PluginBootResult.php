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
 * What a {@see PluginBootStrategyInterface} reports back to the kernel: the instantiated
 * plugins (vetoed ones included), the names that actually booted, and the routes/commands
 * the phase collected. Routes feed the kernel's {@see \Milpa\Http\Routing\Router}; both
 * lists default to empty because host strategies may assemble routes outside the kernel
 * (attribute scanning is a host concern, never a kernel one).
 *
 * @phpstan-type RouteList list<\Milpa\Http\Routing\Route>
 * @phpstan-type OperationList list<\Milpa\Command\Operation>
 */
final readonly class PluginBootResult
{
    /**
     * @param list<object>                    $plugins
     * @param list<string>                    $bootedPluginNames
     * @param list<\Milpa\Http\Routing\Route> $routes
     * @param list<\Milpa\Command\Operation>  $commands
     * @param bool                            $emittedKernelBooted Set by the STRATEGY (never the kernel
     *                                                             itself) to `true` when the delegated boot
     *                                                             cycle it just ran already dispatched
     *                                                             `kernel.booted` on `BootContext::$dispatcher` —
     *                                                             e.g. {@see PluginsManagerBootStrategy}, whose
     *                                                             underlying `PluginsManager::loadPlugins()`
     *                                                             emits it itself. `Kernel::boot()` reads this
     *                                                             flag to skip its own final dispatch, so
     *                                                             listeners always see `kernel.booted` exactly
     *                                                             ONCE per boot, never twice. Defaults to
     *                                                             `false` (BC): every strategy that does not
     *                                                             set it — {@see InlinePluginBootStrategy}
     *                                                             included — keeps the kernel's own emission,
     *                                                             byte-identical to the pre-flag behavior.
     */
    public function __construct(
        public array $plugins,
        public array $bootedPluginNames,
        public array $routes = [],
        public array $commands = [],
        public bool $emittedKernelBooted = false,
    ) {
    }
}
