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

namespace Milpa\Runtime;

use Milpa\Command\Operation;
use Milpa\Container\DIContainer;
use Milpa\Events\KernelBootedEvent;
use Milpa\Eventing\EventDispatcher;
use Milpa\Exceptions\AttributeNotFoundException;
use Milpa\Exceptions\Plugin\PluginDependencyException;
use Milpa\Http\Routing\Router;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Event\MilpaEventDispatcherInterface;
use Milpa\Interfaces\Tooling\ToolRegistryInterface;
use Milpa\Resolver\Engine\GraphResolver;
use Milpa\Resolver\Manifest\HostProfile;
use Milpa\Resolver\Report\ResolutionReport;
use Milpa\Runtime\Boot\BootContext;
use Milpa\Runtime\Boot\InlinePluginBootStrategy;
use Milpa\Runtime\Exceptions\ArchitectureBlockedException;
use Milpa\Runtime\Support\RootResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The fresh, bootable Milpa kernel: COMPOSES the published family instead of reimplementing it.
 *
 * `boot()` wires, in order: a DI container ({@see \Milpa\Container\DIContainer}) -> an event
 * dispatcher ({@see \Milpa\Eventing\EventDispatcher}) -> an architecture resolution through
 * `milpa/resolver`'s {@see GraphResolver} (fails BEFORE any plugin boots, with a learnable message —
 * this replaces the retired {@see \Milpa\Services\CapabilityGraphChecker}, spec §24.7) -> plugins
 * loaded and booted in `provides`-\>`requires` order (the `loadOrder[]` the resolver's own
 * {@see ResolutionReport} dictates — one topological pass, no second sort) -> route table assembly
 * over `milpa/http`. Every step emits the same
 * lifecycle events the legacy host kernel's event-driven retrofit introduced
 * (`plugin.booting`/`plugin.booted`, `capability.resolved`, `kernel.booted` — see {@see \Milpa\Events}),
 * plus the new `architecture.resolved` carrying the resolver's full report.
 *
 * Zero Doctrine, zero legacy `Milpa\Web`: the active-plugins list is whatever `list<class-string>`
 * the caller passes via `$config['plugins']` — a config array, a file `require`d into that array,
 * or filesystem discovery the caller performs before calling `boot()`. This class never queries a
 * database to decide what to boot; a persistence-backed plugin registry is something a *plugin*
 * can add later, never something the kernel requires.
 */
final class Kernel
{
    /**
     * @param list<object>    $plugins           Every instantiated plugin, in the order given by
     *                                           `$config['plugins']` (includes vetoed ones).
     * @param list<string>    $bootedPluginNames Names of plugins whose `boot()` actually ran.
     * @param list<Operation> $commands          Commands collected from every booted
     *                                           `CommandProviderInterface` or `CommandProvider` plugin.
     */
    private function __construct(
        private readonly DIContainerInterface $container,
        private readonly MilpaEventDispatcherInterface $dispatcher,
        private readonly Router $router,
        private readonly array $plugins,
        private readonly array $bootedPluginNames,
        private readonly string $root,
        private readonly ?ToolRegistryInterface $toolRegistry,
        private readonly array $commands,
    ) {
    }

    /**
     * Boots the kernel: builds (or accepts injected) collaborators, resolves the host root, resolves
     * the whole architecture graph through `milpa/resolver` (blocking a bad graph BEFORE any plugin
     * boots), orders the configured plugins, boots each in order while emitting the lifecycle events,
     * and assembles the route table from every booted `RouteProviderInterface` plugin.
     *
     * @param array{
     *     root?: string|null,
     *     name?: string,
     *     plugins?: list<class-string>,
     *     config?: array<string, mixed>,
     *     hostProfile?: array<string, mixed>,
     *     evaluatedAt?: string,
     *     container?: DIContainerInterface,
     *     dispatcher?: MilpaEventDispatcherInterface,
     *     logger?: LoggerInterface,
     *     toolRegistry?: ToolRegistryInterface|null,
     *     pluginBoot?: Boot\PluginBootStrategyInterface,
     * } $config Every key is optional: `root` defaults to {@see RootResolver}'s auto-detection,
     *            `plugins` defaults to an empty list, `config` is the app-config bag plugins read via
     *            `$container->get(Config::class)` (the seam that replaces plugin constructor args and
     *            env-var globals), `container`/`dispatcher`/`logger` default to fresh family instances
     *            (injecting your own is the seam tests use to observe lifecycle events before `boot()`
     *            runs), `toolRegistry` defaults to null — wiring one is the host's opt-in,
     *            `milpa/runtime` never constructs one itself. `hostProfile` (a {@see HostProfile::fromArray()}
     *            shape) is the architectural profile the resolver checks against; ABSENT it defaults to a
     *            DELIBERATELY PERMISSIVE profile — name `$config['name']` or `'host'`, version `0.0.0`,
     *            `allowedLegacyContracts: ['*']`, NO `requiredCapabilities` — so a graph that boots today
     *            (plugin `requires` all satisfied by some plugin's `provides`) resolves identically and BC
     *            holds. `evaluatedAt` (an ISO-8601 datetime) is passed straight through to the resolver as
     *            the clock for accepted-risk expiry; the resolver, not the runtime, validates it.
     *
     * A blocked graph throws {@see ArchitectureBlockedException} — a {@see PluginDependencyException}
     * subclass, so every catch that worked against the retired
     * {@see \Milpa\Services\CapabilityGraphChecker} still works (narrowing BC) — with the report's own
     * learnable first line as the message (code + why + first fix + Academy link) and the whole
     * {@see ResolutionReport} riding on `->report`. Boot listeners still get the report on the
     * `architecture.resolved` event when the graph is NOT blocked.
     *
     * @throws AttributeNotFoundException                          A configured plugin class carries no `#[PluginMetadata]`.
     * @throws ArchitectureBlockedException                        The architecture graph is `blocked` (an unmet required
     *                                                             contract/capability, a conflict — a dependency cycle included —
     *                                                             or an un-permitted legacy path); carries the full report.
     * @throws \Milpa\Resolver\Exceptions\InvalidManifestException The `hostProfile` array or the `evaluatedAt` clock is malformed,
     *                                                             or a plugin's `PluginMetadata` version is not a parseable version
     *                                                             string (all validated by the resolver, not the runtime).
     * @throws \Milpa\Runtime\Support\RootNotFoundException        The host root could not be resolved and none was given explicitly.
     */
    public static function boot(array $config = []): self
    {
        $logger = $config['logger'] ?? new NullLogger();
        $container = $config['container'] ?? new DIContainer();
        $dispatcher = $config['dispatcher'] ?? new EventDispatcher($logger);
        $container->registerService(MilpaEventDispatcherInterface::class, $dispatcher);

        $root = (new RootResolver($config['root'] ?? null))->resolve();

        // App config bag: plugins read their own configuration here in boot() — the seam that
        // replaces constructor args (PluginInterface fixes the ctor to ($container)) and env-var
        // globals. `$container->get(Config::class)->get('storage.path')`.
        $container->registerService(Config::class, new Config($config['config'] ?? []));

        $toolRegistry = $config['toolRegistry'] ?? null;
        if ($toolRegistry !== null) {
            $container->registerService(ToolRegistryInterface::class, $toolRegistry);
        }

        // The whole plugin phase — instantiation, architecture gate, ordering, lifecycle loop —
        // lives behind the strategy seam; the inline default is byte-compatible with the
        // pre-seam kernel.
        $strategy = $config['pluginBoot'] ?? new InlinePluginBootStrategy();
        $result = $strategy->bootPlugins(new BootContext($container, $dispatcher, $root, $config, $toolRegistry));

        $router = new Router(...$result->routes);

        // Skip when the strategy already emitted it on this SAME dispatcher (see
        // PluginBootResult::$emittedKernelBooted) — otherwise listeners would see it TWICE.
        if (!$result->emittedKernelBooted) {
            $dispatcher->dispatch('kernel.booted', ['event' => new KernelBootedEvent($result->bootedPluginNames)]);
        }

        return new self($container, $dispatcher, $router, $result->plugins, $result->bootedPluginNames, $root, $toolRegistry, $result->commands);
    }

    /** The DI container every plugin and controller was resolved through. */
    public function container(): DIContainerInterface
    {
        return $this->container;
    }

    /** The event dispatcher every lifecycle event was emitted on. */
    public function dispatcher(): MilpaEventDispatcherInterface
    {
        return $this->dispatcher;
    }

    /** The route table assembled from every booted `RouteProviderInterface` plugin. */
    public function router(): Router
    {
        return $this->router;
    }

    /**
     * Every configured plugin instance, including any vetoed via a `plugin.booting` listener.
     *
     * @return list<object>
     */
    public function plugins(): array
    {
        return $this->plugins;
    }

    /**
     * Names of the plugins whose `boot()` actually ran, in boot order.
     *
     * @return list<string>
     */
    public function bootedPluginNames(): array
    {
        return $this->bootedPluginNames;
    }

    /** The resolved host application root directory. */
    public function root(): string
    {
        return $this->root;
    }

    /** The tool registry wired via `$config['toolRegistry']`, or null if the host opted out. */
    public function toolRegistry(): ?ToolRegistryInterface
    {
        return $this->toolRegistry;
    }

    /**
     * Every command collected from a booted `CommandProviderInterface` plugin's `commands()`, or a
     * `CommandProvider` plugin's `operations()` — the command-table counterpart of {@see router()}.
     * A host CLI registers these as subcommands in addition to its own built-ins.
     *
     * @return list<Operation>
     */
    public function commands(): array
    {
        return $this->commands;
    }
}
