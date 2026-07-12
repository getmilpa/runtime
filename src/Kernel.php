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
use Milpa\Exceptions\Plugin\PluginDependencyException;
use Milpa\Http\Routing\Router;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Event\MilpaEventDispatcherInterface;
use Milpa\Interfaces\Plugin\PluginInterface;
use Milpa\Interfaces\Tooling\ToolProviderInterface;
use Milpa\Interfaces\Tooling\ToolRegistryInterface;
use Milpa\Plugin\ContractResolver;
use Milpa\Resolver\Engine\GraphResolver;
use Milpa\Resolver\Events\ArchitectureResolvedEvent;
use Milpa\Resolver\Ingest\AttributeLoader;
use Milpa\Resolver\Input\ResolutionInput;
use Milpa\Resolver\Manifest\HostProfile;
use Milpa\Resolver\Report\ResolutionReport;
use Milpa\Resolver\Report\ResolutionStatus;
use Milpa\Runtime\Http\RouteProviderInterface;
use Milpa\Runtime\Support\RootResolver;
use Milpa\ValueObjects\Capability\CapabilityRequirement;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The fresh, bootable Milpa kernel: COMPOSES the published family instead of reimplementing it.
 *
 * `boot()` wires, in order: a DI container ({@see \Milpa\Container\DIContainer}) -> an event
 * dispatcher ({@see \Milpa\Eventing\EventDispatcher}) -> an architecture resolution through
 * `milpa/resolver`'s {@see GraphResolver} (fails BEFORE any plugin boots, with a learnable message —
 * this replaces the retired {@see \Milpa\Services\CapabilityGraphChecker}, spec §24.7) -> plugins
 * loaded and booted in `provides`-\>`requires` order (ordering computed by `milpa/plugin`'s
 * {@see ContractResolver}) -> route table assembly over `milpa/http`. Every step emits the same
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
     * A blocked graph throws {@see PluginDependencyException} — the SAME exception TYPE the retired
     * {@see \Milpa\Services\CapabilityGraphChecker} threw (BC) — but now with a learnable message
     * (code + why + first fix + Academy link). runtime 0.4 will raise a typed exception carrying the
     * whole {@see ResolutionReport} instead; until then the report is available to listeners on the new
     * `architecture.resolved` event.
     *
     * @throws AttributeNotFoundException                          A configured plugin class carries no `#[PluginMetadata]`.
     * @throws PluginDependencyException                           The architecture graph is `blocked` (an unmet required
     *                                                             contract/capability, a conflict, or an un-permitted legacy path).
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

        /** @var list<class-string> $pluginClasses */
        $pluginClasses = $config['plugins'] ?? [];
        $plugins = self::instantiatePlugins($pluginClasses, $container);

        // Reflect each plugin's metadata ONCE here; the resolver, the load-order and the boot loop all
        // reuse it (no plugin is reflected twice).
        [$metadataArrays, $pluginsByClass, $metadata] = self::describePlugins($plugins);

        // Architecture gate FIRST, before anything is booted (design mandate "falla pre-boot", spec
        // §24.7): resolve the whole graph through milpa/resolver — a blocked graph throws a LEARNABLE
        // PluginDependencyException (the SAME exception TYPE the retired CapabilityGraphChecker threw = BC).
        $report = self::resolveArchitecture($metadata, $config);
        if ($report->status === ResolutionStatus::Blocked) {
            throw new PluginDependencyException(self::learnableMessage($report));
        }

        $loadOrder = (new ContractResolver($logger))->getLoadOrder($metadataArrays);

        // The report travels to boot listeners on its OWN event ('architecture.resolved') — milpa/core's
        // CapabilityResolvedEvent is frozen and could not carry it. It is dispatched BEFORE the
        // byte-identical, BC 'capability.resolved' so the old listeners see exactly what they saw before.
        $dispatcher->dispatch('architecture.resolved', ['event' => new ArchitectureResolvedEvent($report)]);
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
     * Reflect each plugin's `#[PluginMetadata]` ONCE and return three parallel views of it: the flat
     * arrays `ContractResolver::getLoadOrder()` consumes, the class-\>instance map the boot loop indexes,
     * and the raw {@see PluginMetadata} records the architecture resolver ingests — so no plugin is ever
     * reflected twice.
     *
     * @param list<PluginInterface> $plugins
     *
     * @return array{0: list<array{name: string, class: string, provides: array<class-string>, requires: array<class-string>, suggests: array<class-string>}>, 1: array<class-string, PluginInterface>, 2: list<PluginMetadata>}
     */
    private static function describePlugins(array $plugins): array
    {
        $metadataArrays = [];
        $pluginsByClass = [];
        $metadata = [];
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
            $metadata[] = $meta;
        }

        return [$metadataArrays, $pluginsByClass, $metadata];
    }

    /**
     * Resolve the configured plugins' architecture through `milpa/resolver`: each plugin's
     * `#[PluginMetadata]` becomes a {@see \Milpa\Resolver\Manifest\VersionManifest} via
     * {@see AttributeLoader::fromMetadata()} (its `provides` become the available providers), and each
     * plugin's `requires` becomes a {@see CapabilityRequirement} the graph must close — the versioned,
     * legacy-aware successor to the old `provides`/`requires` identity check. The host profile comes from
     * `$config['hostProfile']` or defaults to the permissive profile (see {@see boot()}); `evaluatedAt`
     * rides through untouched for the resolver to validate.
     *
     * @param list<PluginMetadata> $metadata
     * @param array<string, mixed> $config
     */
    private static function resolveArchitecture(array $metadata, array $config): ResolutionReport
    {
        $loader = new AttributeLoader();
        $manifests = [];
        $requirements = [];
        foreach ($metadata as $meta) {
            $manifests[] = $loader->fromMetadata($meta);
            foreach ($meta->requires as $interface) {
                $requirements[] = CapabilityRequirement::fromInterface($interface);
            }
        }

        return (new GraphResolver())->resolve(new ResolutionInput(
            hostProfile: self::hostProfileFrom($config),
            versionManifests: $manifests,
            contractManifests: [],
            capabilityProvisions: [],
            capabilityRequirements: $requirements,
            evaluatedAt: is_string($config['evaluatedAt'] ?? null) ? $config['evaluatedAt'] : null,
        ));
    }

    /**
     * The {@see HostProfile} to resolve against: `$config['hostProfile']` verbatim if given, else the
     * DELIBERATELY PERMISSIVE default — name `$config['name']` or `'host'`, version `0.0.0`, every legacy
     * path allowed (`['*']`), no required capabilities — the profile under which a graph that boots today
     * resolves identically (BC).
     *
     * @param array<string, mixed> $config
     */
    private static function hostProfileFrom(array $config): HostProfile
    {
        $profile = $config['hostProfile'] ?? null;
        if (is_array($profile)) {
            return HostProfile::fromArray($profile);
        }

        $name = is_string($config['name'] ?? null) && $config['name'] !== '' ? $config['name'] : 'host';

        return new HostProfile(name: $name, version: '0.0.0', allowedLegacyContracts: ['*']);
    }

    /**
     * Render the first learnable error of a blocked report into the exception message: its code, human
     * message, the concept it violated (`why`), the first concrete fix, and the English Academy link — so
     * a blocked boot teaches the reader the way `coa:inspect architecture` does, instead of merely failing.
     */
    private static function learnableMessage(ResolutionReport $report): string
    {
        $first = $report->errors[0] ?? null;
        if ($first === null) {
            return 'The architecture graph is blocked; the host cannot boot until every required dependency closes.';
        }

        $fix = $first->fixes[0] ?? '';
        $academy = $first->links['academy'] ?? null;
        $learn = is_array($academy) && is_string($academy['en'] ?? null) ? $academy['en'] : '';

        return sprintf('%s: %s — %s Fix: %s Learn: %s', $first->code, $first->message, $first->why, $fix, $learn);
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
