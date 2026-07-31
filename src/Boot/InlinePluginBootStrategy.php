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

use Milpa\Attributes\PluginMetadata;
use Milpa\Command\CommandProvider;
use Milpa\Command\Operation;
use Milpa\Events\CapabilityResolvedEvent;
use Milpa\Events\InterceptionSlot;
use Milpa\Events\PluginBootedEvent;
use Milpa\Events\PluginBootingEvent;
use Milpa\Exceptions\AttributeNotFoundException;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Event\MilpaEventDispatcherInterface;
use Milpa\Interfaces\Plugin\PluginInterface;
use Milpa\Interfaces\Tooling\ToolProviderInterface;
use Milpa\Interfaces\Tooling\ToolRegistryInterface;
use Milpa\Resolver\Engine\GraphResolver;
use Milpa\Resolver\Events\ArchitectureResolvedEvent;
use Milpa\Resolver\Exceptions\InvalidManifestException;
use Milpa\Resolver\Ingest\AttributeLoader;
use Milpa\Resolver\Input\ResolutionInput;
use Milpa\Resolver\Manifest\HostProfile;
use Milpa\Resolver\Report\ResolutionReport;
use Milpa\Resolver\Report\ResolutionStatus;
use Milpa\Runtime\CommandProviderInterface;
use Milpa\Runtime\Exceptions\ArchitectureBlockedException;
use Milpa\Runtime\Http\RouteProviderInterface;
use Milpa\ValueObjects\Capability\CapabilityRequirement;

/**
 * The pre-seam kernel plugin phase, verbatim: instantiate `$config['plugins']`, reflect
 * `#[PluginMetadata]` once, gate the architecture through milpa/resolver (blocked graph
 * throws BEFORE anything boots), follow the report's loadOrder, and run the lifecycle
 * boot loop. Byte-compatible with the kernel before the strategy seam existed.
 */
final class InlinePluginBootStrategy implements PluginBootStrategyInterface
{
    /**
     * Resolves the declared plugin list, validates it, and boots it in dependency order.
     *
     * Everything happens here: there is no manager to delegate to, which is what makes this the
     * strategy a host gets for free. Routes, commands and operations are collected from each
     * plugin that declares them, as it boots.
     */
    public function bootPlugins(BootContext $context): PluginBootResult
    {
        $config = $context->config;

        /** @var list<class-string> $pluginClasses */
        $pluginClasses = $config['plugins'] ?? [];
        $plugins = self::instantiatePlugins($pluginClasses, $context->container);

        // Reflect each plugin's metadata ONCE here; the resolver, the load-order and the boot loop all
        // reuse it (no plugin is reflected twice).
        [$metadataArrays, $pluginsByClass, $metadata] = self::describePlugins($plugins);

        // Architecture gate FIRST, before anything is booted (design mandate "falla pre-boot", spec
        // §24.7): resolve the whole graph through milpa/resolver — a blocked graph throws the typed
        // ArchitectureBlockedException (a PluginDependencyException, so every existing catch = BC)
        // carrying the full report, with the report's own learnable first line as the message.
        $report = self::resolveArchitecture($metadata, $config);
        if ($report->status === ResolutionStatus::Blocked) {
            throw new ArchitectureBlockedException(
                $report,
                $report->firstLearnableLine()
                    ?? 'The architecture graph is blocked; the host cannot boot until every required dependency closes.',
            );
        }

        // The SAME resolution that gated the graph also ordered it: follow the report's loadOrder[]
        // (provides -> requires, ties in config order) projected onto the full plugin records.
        $loadOrder = self::orderFromReport($report, $metadataArrays);

        // The report travels to boot listeners on its OWN event ('architecture.resolved') — milpa/core's
        // CapabilityResolvedEvent is frozen and could not carry it. It is dispatched BEFORE the
        // byte-identical, BC 'capability.resolved' so the old listeners see exactly what they saw before.
        $context->dispatcher->dispatch('architecture.resolved', ['event' => new ArchitectureResolvedEvent($report)]);
        $context->dispatcher->dispatch('capability.resolved', ['event' => new CapabilityResolvedEvent($loadOrder)]);

        [$bootedNames, $routes, $commands] = self::runBootLoop($loadOrder, $pluginsByClass, $context->dispatcher, $context->toolRegistry);

        return new PluginBootResult(
            plugins: $plugins,
            bootedPluginNames: $bootedNames,
            routes: $routes,
            commands: $commands,
        );
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
     * records {@see orderFromReport()} projects the report's `loadOrder[]` onto, the class-\>instance
     * map the boot loop indexes, and the raw {@see PluginMetadata} records the architecture resolver
     * ingests — so no plugin is ever reflected twice.
     *
     * @param list<PluginInterface> $plugins
     *
     * @return array{0: list<array{name: string, class: string, provides: array<class-string|array<string, mixed>>, requires: array<class-string|array<string, mixed>>, suggests: array<class-string|array<string, mixed>>}>, 1: array<class-string, PluginInterface>, 2: list<PluginMetadata>}
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
     * legacy-aware successor to the old `provides`/`requires` identity check. A `requires` entry comes
     * in BOTH real shapes `#[PluginMetadata]` sanctions — a legacy bare FQCN string or a canonical
     * requirement record — so each dispatches through {@see CapabilityRequirement::parse()} (the same
     * seam {@see AttributeLoader} uses): a rich record becomes a real requirement instead of
     * raw-TypeError-ing `fromInterface()`. The host profile comes from `$config['hostProfile']` or
     * defaults to the permissive profile (see {@see boot()}); `evaluatedAt` rides through untouched
     * for the resolver to validate.
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
            foreach ($meta->requires as $index => $entry) {
                $requirements[] = CapabilityRequirement::parse(self::requirementEntry($entry, $meta->name, $index));
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
     * Assert a `requires` entry is a parseable shape — a bare FQCN string or a structured record —
     * mirroring {@see AttributeLoader}'s guard so both consumers of `#[PluginMetadata]` teach the
     * same lesson. In practice {@see AttributeLoader::fromMetadata()} has already taught it for the
     * same metadata one line up in {@see resolveArchitecture()}; this keeps the Kernel's own seam
     * honest (and typed for {@see CapabilityRequirement::parse()}) on its own terms.
     *
     * @return string|array<string, mixed>
     *
     * @throws \Milpa\Resolver\Exceptions\InvalidManifestException When the entry is neither a string nor an array.
     */
    private static function requirementEntry(mixed $entry, string $plugin, int|string $index): string|array
    {
        if (is_string($entry) || is_array($entry)) {
            /** @var string|array<string, mixed> $entry */
            return $entry;
        }

        throw InvalidManifestException::malformed($plugin, sprintf(
            '#[PluginMetadata] requires entry #%s must be an FQCN string or a record object.',
            (string) $index,
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
     * Project the report's `loadOrder[]` — the boot sequence the SAME resolution that gated the
     * architecture computed (`provides` -> `requires`, ties in config order) — onto the full
     * {@see describePlugins()} records, so the boot loop and the BC `capability.resolved` payload
     * consume the exact arrays they always consumed, now sequenced by the report.
     *
     * Defensive by contract, never silent: both sides derive from the SAME `#[PluginMetadata]`
     * reflection pass, so every `loadOrder` name maps to a record — and every record appears in
     * `loadOrder`, because the only entries the resolver ever excludes are dependency-cycle members
     * and a cycle means `blocked`, already thrown at the gate. A mismatch in either direction —
     * including two plugins sharing a metadata `name`, which would collapse into one sequenced entry —
     * means the boot would silently skip or drop a plugin, so this throws instead.
     *
     * @param list<array{name: string, class: string, provides: array<class-string|array<string, mixed>>, requires: array<class-string|array<string, mixed>>, suggests: array<class-string|array<string, mixed>>}> $metadataArrays
     *
     * @return list<array{name: string, class: string, provides: array<class-string|array<string, mixed>>, requires: array<class-string|array<string, mixed>>, suggests: array<class-string|array<string, mixed>>}>
     */
    private static function orderFromReport(ResolutionReport $report, array $metadataArrays): array
    {
        $recordsByName = [];
        foreach ($metadataArrays as $record) {
            $recordsByName[$record['name']] = $record;
        }

        $ordered = [];
        foreach ($report->loadOrder as $entry) {
            $name = $entry['name'] ?? null;
            if (!is_string($name) || !isset($recordsByName[$name])) {
                throw new \LogicException(sprintf(
                    "The resolver's loadOrder names '%s', but no configured plugin carries that #[PluginMetadata] name — the resolver and the runtime disagreed about the inputs.",
                    is_string($name) ? $name : get_debug_type($name),
                ));
            }
            $ordered[] = $recordsByName[$name];
        }

        if (count($ordered) !== count($metadataArrays)) {
            throw new \LogicException(sprintf(
                "The resolver's loadOrder sequences %d of the %d configured plugins; a dependency cycle would have blocked the gate, so a plugin was dropped without a diagnosis — the resolver and the runtime disagreed about the inputs (or two plugins share a #[PluginMetadata] name).",
                count($ordered),
                count($metadataArrays),
            ));
        }

        return $ordered;
    }

    /**
     * Runs the boot loop over the dependency-ordered plugin list: emits `plugin.booting`
     * (stoppable), calls `boot()` unless vetoed, emits `plugin.booted`, then collects routes,
     * collects commands, and registers tools for the plugins that actually booted.
     *
     * @param array<array{name: string, class: string, provides?: array<string|array<string, mixed>>, requires?: array<string|array<string, mixed>>}> $loadOrder      The {@see describePlugins()}
     *                                                                                                                                                                records in the order {@see orderFromReport()} projected from the report's `loadOrder[]`.
     * @param array<class-string, PluginInterface>                                                                                                    $pluginsByClass
     *
     * @return array{0: list<string>, 1: list<\Milpa\Http\Routing\Route>, 2: list<Operation>}
     */
    private static function runBootLoop(
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
