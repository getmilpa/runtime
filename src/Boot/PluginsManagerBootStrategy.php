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

use Milpa\Interfaces\Plugin\PluginsManagerInterface;

/**
 * Delegates the kernel's plugin phase to a host-grade plugins manager (milpa/plugin's
 * `PluginsManager` or any {@see PluginsManagerInterface}): registry-driven activation,
 * resolver gate, two-layer caches, environment-gated tool registration and
 * `EventSubscriberInterface` auto-subscription all happen INSIDE the manager — this
 * strategy never re-implements them (Ola 7 D1).
 *
 * Routes and commands are intentionally NOT collected here: a host on this strategy
 * assembles its route table outside the kernel (attribute scanning is a host concern),
 * so {@see PluginBootResult::$routes} stays empty and the kernel's router boots empty.
 */
final readonly class PluginsManagerBootStrategy implements PluginBootStrategyInterface
{
    public function __construct(
        private PluginsManagerInterface $manager,
        private string $pluginsPath,
    ) {
    }

    public function bootPlugins(BootContext $context): PluginBootResult
    {
        // Same registration a host's own boot orchestrator performs (e.g. this repo's extinct
        // WebManager/CliManager, retired Ola 7c): plugins and commands resolve the manager
        // from the container during and after boot.
        $context->container->registerService(PluginsManagerInterface::class, $this->manager);

        $this->manager->addPluginPath($this->pluginsPath);
        $this->manager->loadPlugins();

        $instances = $this->manager->getPlugins();

        return new PluginBootResult(
            plugins: array_values($instances),
            bootedPluginNames: array_keys($instances),
            // PluginsManager::loadPlugins() already emitted 'kernel.booted' on this SAME dispatcher
            // (cache and fresh paths alike) — the kernel must not re-emit it.
            emittedKernelBooted: true,
        );
    }
}
