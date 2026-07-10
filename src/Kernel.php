<?php

declare(strict_types=1);

namespace Milpa\Runtime;

use Milpa\Attributes\PluginMetadata;
use Milpa\Command\CommandProvider;
use Milpa\Command\Operation;
use Milpa\Container\DIContainer;
use Milpa\Events\CapabilityResolvedEvent;
use Milpa\Events\InterceptionSlot;
use Milpa\Events\KernelBootedEvent;
use Milpa\Events\PluginBootedEvent;
use Milpa\Events\PluginBootingEvent;
use Milpa\Eventing\EventDispatcher;
use Milpa\Exceptions\AttributeNotFoundException;
use Milpa\Http\Routing\Router;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Event\MilpaEventDispatcherInterface;
use Milpa\Interfaces\Plugin\PluginInterface;
use Milpa\Interfaces\Tooling\ToolProviderInterface;
use Milpa\Interfaces\Tooling\ToolRegistryInterface;
use Milpa\Plugin\ContractResolver;
use Milpa\Runtime\Http\RouteProviderInterface;
use Milpa\Runtime\Support\RootResolver;
use Milpa\Services\CapabilityGraphChecker;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The fresh, bootable Milpa kernel: COMPOSES the published family instead of reimplementing it.
 *
 * `boot()` wires, in order: a DI container ({@see \Milpa\Container\DIContainer}) -> an event
 * dispatcher ({@see \Milpa\Eventing\EventDispatcher}) -> a capability-graph check
 * ({@see CapabilityGraphChecker}, fails BEFORE any plugin boots) -> plugins loaded and booted in
 * `provides`-\>`requires` order (ordering computed by `milpa/plugin`'s {@see ContractResolver}) ->
 * route table assembly over `milpa/http`. Every step emits the same lifecycle events the legacy
 * host kernel's event-driven retrofit introduced (`plugin.booting`/`plugin.booted`,
 * `capability.resolved`, `kernel.booted` — see {@see \Milpa\Events}).
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
     * Boots the kernel: builds (or accepts injected) collaborators, resolves the host root,
     * capability-checks and orders the configured plugins, boots each in order while emitting
     * the lifecycle events, and assembles the route table from every booted `RouteProviderInterface`
     * plugin.
     *
     * @param array{
     *     root?: string|null,
     *     plugins?: list<class-string>,
     *     config?: array<string, mixed>,
     *     container?: DIContainerInterface,
     *     dispatcher?: MilpaEventDispatcherInterface,
     *     logger?: LoggerInterface,
     *     toolRegistry?: ToolRegistryInterface|null,
     * } $config Every key is optional: `root` defaults to {@see RootResolver}'s auto-detection,
     *            `plugins` defaults to an empty list, `config` is the app-config bag plugins read via
     *            `$container->get(Config::class)` (the seam that replaces plugin constructor args and
     *            env-var globals), `container`/`dispatcher`/`logger` default to fresh family instances
     *            (injecting your own is the seam tests use to observe lifecycle events before `boot()`
     *            runs), `toolRegistry` defaults to null — wiring one is the host's opt-in,
     *            `milpa/runtime` never constructs one itself.
     *
     * @throws AttributeNotFoundException                         A configured plugin class carries no `#[PluginMetadata]`.
     * @throws \Milpa\Exceptions\Plugin\PluginDependencyException A plugin `requires` a capability no configured plugin `provides`.
     * @throws \Milpa\Runtime\Support\RootNotFoundException       The host root could not be resolved and none was given explicitly.
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

        /** @var list<class-string> $pluginClasses */
        $pluginClasses = $config['plugins'] ?? [];
        $plugins = self::instantiatePlugins($pluginClasses, $container);

        // Capability check FIRST, before anything is booted (design mandate: "falla pre-boot").
        (new CapabilityGraphChecker())->check($plugins);

        [$metadataArrays, $pluginsByClass] = self::describePlugins($plugins);
        $loadOrder = (new ContractResolver($logger))->getLoadOrder($metadataArrays);

        $dispatcher->dispatch('capability.resolved', ['event' => new CapabilityResolvedEvent($loadOrder)]);

        [$bootedNames, $routes, $commands] = self::bootPlugins($loadOrder, $pluginsByClass, $dispatcher, $toolRegistry);

        $router = new Router(...$routes);

        $dispatcher->dispatch('kernel.booted', ['event' => new KernelBootedEvent($bootedNames)]);

        return new self($container, $dispatcher, $router, $plugins, $bootedNames, $root, $toolRegistry, $commands);
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

    /**
     * @param list<class-string> $pluginClasses
     *
     * @return list<PluginInterface>
     */
    private static function instantiatePlugins(array $pluginClasses, DIContainerInterface $container): array
    {
        $plugins = [];
        foreach ($pluginClasses as $pluginClass) {
            $plugin = new $pluginClass($container);
            if (!$plugin instanceof PluginInterface) {
                throw new \RuntimeException(\sprintf(
                    '%s must implement %s to be booted by %s.',
                    $pluginClass,
                    PluginInterface::class,
                    self::class,
                ));
            }
            $plugins[] = $plugin;
        }

        return $plugins;
    }

    /**
     * @param list<PluginInterface> $plugins
     *
     * @return array{0: list<array{name: string, class: string, provides: array<class-string>, requires: array<class-string>, suggests: array<class-string>}>, 1: array<class-string, PluginInterface>}
     */
    private static function describePlugins(array $plugins): array
    {
        $metadataArrays = [];
        $pluginsByClass = [];
        foreach ($plugins as $plugin) {
            $meta = self::metadataOf($plugin);
            $metadataArrays[] = [
                'name' => $meta->name,
                'class' => $plugin::class,
                'provides' => $meta->provides,
                'requires' => $meta->requires,
                'suggests' => $meta->suggests,
            ];
            $pluginsByClass[$plugin::class] = $plugin;
        }

        return [$metadataArrays, $pluginsByClass];
    }

    /**
     * Runs the boot loop over the dependency-ordered plugin list: emits `plugin.booting`
     * (stoppable), calls `boot()` unless vetoed, emits `plugin.booted`, then collects routes,
     * collects commands, and registers tools for the plugins that actually booted.
     *
     * @param array<array{name: string, class: string, provides?: array<string>, requires?: array<string>}> $loadOrder      Same shape
     *                                                                                                                      `ContractResolver::getLoadOrder()` returns — not declared `list<>`, mirroring its own return type exactly.
     * @param array<class-string, PluginInterface>                                                          $pluginsByClass
     *
     * @return array{0: list<string>, 1: list<\Milpa\Http\Routing\Route>, 2: list<Operation>}
     */
    private static function bootPlugins(
        array $loadOrder,
        array $pluginsByClass,
        MilpaEventDispatcherInterface $dispatcher,
        ?ToolRegistryInterface $toolRegistry,
    ): array {
        $bootedNames = [];
        $routes = [];
        $commands = [];

        foreach ($loadOrder as $entry) {
            $plugin = $pluginsByClass[$entry['class']];
            $name = $entry['name'];
            /** @var array<string, mixed> $metadataPayload */
            $metadataPayload = [
                'name' => $entry['name'],
                'provides' => $entry['provides'] ?? [],
                'requires' => $entry['requires'] ?? [],
            ];

            $slot = new InterceptionSlot();
            $dispatcher->dispatch(
                'plugin.booting',
                ['event' => new PluginBootingEvent($name, $metadataPayload), 'slot' => $slot],
            );
            if ($slot->isStopped()) {
                continue;
            }

            $plugin->boot();
            $bootedNames[] = $name;
            $dispatcher->dispatch('plugin.booted', ['event' => new PluginBootedEvent($name, $metadataPayload)]);

            if ($plugin instanceof RouteProviderInterface) {
                foreach ($plugin->routes() as $route) {
                    $routes[] = $route;
                }
            }
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
            if ($toolRegistry !== null && $plugin instanceof ToolProviderInterface) {
                $plugin->registerTools($toolRegistry);
            }
        }

        return [$bootedNames, $routes, $commands];
    }

    /** @throws AttributeNotFoundException */
    private static function metadataOf(object $plugin): PluginMetadata
    {
        $attributes = (new \ReflectionClass($plugin))->getAttributes(PluginMetadata::class);
        if ($attributes === []) {
            throw new AttributeNotFoundException(
                $plugin::class . ' has no #[PluginMetadata] attribute.'
            );
        }

        return $attributes[0]->newInstance();
    }
}
