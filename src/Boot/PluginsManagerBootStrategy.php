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

use Milpa\Command\CommandProvider;
use Milpa\Interfaces\Plugin\PluginsManagerInterface;
use Milpa\Runtime\CommandProviderInterface;

/**
 * Delegates the kernel's plugin phase to a host-grade plugins manager (milpa/plugin's
 * `PluginsManager` or any {@see PluginsManagerInterface}): registry-driven activation,
 * resolver gate, two-layer caches, environment-gated tool registration and
 * `EventSubscriberInterface` auto-subscription all happen INSIDE the manager — this
 * strategy never re-implements them (Ola 7 D1).
 *
 * Routes are intentionally NOT collected here: a host on this strategy assembles its route
 * table outside the kernel (attribute scanning is a host concern), so
 * {@see PluginBootResult::$routes} stays empty and the kernel's router boots empty.
 *
 * Commands ARE collected, exactly as {@see InlinePluginBootStrategy} collects them. That
 * sentence used to read "routes and commands", and the exclusion of commands rode in on the
 * conjunction: the reason given —attribute scanning belongs to the host— is true of routes and
 * of nothing else. A route is DISCOVERED by scanning; an operation is DECLARED by a plugin that
 * already booted, and there is nothing left for a host to decide about it. The cost of the
 * silent half of that sentence was measurable: `milpa/plugin` shipped seven plugin-management
 * operations that no host on this strategy could ever see.
 */
final readonly class PluginsManagerBootStrategy implements PluginBootStrategyInterface
{
    public function __construct(
        private PluginsManagerInterface $manager,
        private string $pluginsPath,
    ) {
    }

    /**
     * Hands the plugin phase to the manager and collects the operations the booted plugins declare.
     *
     * The manager decides WHAT boots — registry, resolver gate, caches, environment gating; this
     * method never second-guesses it. What it does afterwards is read: every booted plugin that
     * declares commands or operations contributes them to the kernel's command table.
     */
    public function bootPlugins(BootContext $context): PluginBootResult
    {
        // Same registration a host's own boot orchestrator performs (e.g. this repo's extinct
        // WebManager/CliManager, retired Ola 7c): plugins and commands resolve the manager
        // from the container during and after boot.
        $context->container->registerService(PluginsManagerInterface::class, $this->manager);

        $this->manager->addPluginPath($this->pluginsPath);
        $this->manager->loadPlugins();

        $instances = $this->manager->getPlugins();

        // Same "instanceof, then auto-wire" pass the sister strategy runs. Both contracts feed one
        // list because `CommandDefinition` extends `Operation`: what a projector receives is an
        // operation either way, and which interface declared it is not its business.
        $commands = [];
        foreach ($instances as $plugin) {
            if ($plugin instanceof CommandProviderInterface) {
                foreach ($plugin->commands() as $command) {
                    $commands[] = $command;
                }
            }
            if ($plugin instanceof CommandProvider) {
                foreach ($plugin->operations() as $operation) {
                    $commands[] = $operation;
                }
            }
        }

        return new PluginBootResult(
            plugins: array_values($instances),
            bootedPluginNames: array_keys($instances),
            commands: $commands,
            // PluginsManager::loadPlugins() already emitted 'kernel.booted' on this SAME dispatcher
            // (cache and fresh paths alike) — the kernel must not re-emit it.
            emittedKernelBooted: true,
        );
    }
}
