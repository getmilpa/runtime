<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests;

use Milpa\Container\DIContainer;
use Milpa\Events\CapabilityResolvedEvent;
use Milpa\Events\InterceptionSlot;
use Milpa\Events\KernelBootedEvent;
use Milpa\Events\PluginBootedEvent;
use Milpa\Events\PluginBootingEvent;
use Milpa\Eventing\EventDispatcher;
use Milpa\Exceptions\Plugin\PluginDependencyException;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Event\MilpaEventDispatcherInterface;
use Milpa\Runtime\Kernel;
use Milpa\Runtime\Tests\Fixtures\CommandProvidingPlugin;
use Milpa\Runtime\Tests\Fixtures\DependentPlugin;
use Milpa\Runtime\Tests\Fixtures\OperationProvidingPlugin;
use Milpa\Runtime\Tests\Fixtures\ProvidingPlugin;
use Milpa\Runtime\Tests\Fixtures\RecordingToolRegistry;
use Milpa\Runtime\Tests\Fixtures\RequiringPlugin;
use Milpa\Runtime\Tests\Fixtures\ToolProvidingPlugin;
use Milpa\Runtime\Tests\Fixtures\VetoingSubscriberPlugin;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class KernelBootTest extends TestCase
{
    protected function setUp(): void
    {
        ProvidingPlugin::$bootCount = 0;
        DependentPlugin::$bootOrder = [];
        RequiringPlugin::$booted = false;
        VetoingSubscriberPlugin::$booted = false;
    }

    public function testBootWiresTheFamilyCollaboratorsAndBootsAConfiguredPlugin(): void
    {
        $kernel = Kernel::boot(['plugins' => [ProvidingPlugin::class]]);

        $this->assertInstanceOf(DIContainerInterface::class, $kernel->container());
        $this->assertInstanceOf(MilpaEventDispatcherInterface::class, $kernel->dispatcher());
        $this->assertSame(1, ProvidingPlugin::$bootCount);
        $this->assertSame(['ProvidingPlugin'], $kernel->bootedPluginNames());
        $this->assertCount(1, $kernel->plugins());
        $this->assertNotSame('', $kernel->root());
        $this->assertNull($kernel->toolRegistry());
    }

    public function testCapabilityCheckFailsPreBootWhenARequirementHasNoProvider(): void
    {
        $this->expectException(PluginDependencyException::class);

        try {
            Kernel::boot(['plugins' => [RequiringPlugin::class]]);
        } finally {
            $this->assertFalse(RequiringPlugin::$booted, 'boot() must never run once the pre-boot capability check fails');
        }
    }

    public function testCapabilityCheckPassesAndBootsInProvidesRequiresOrderWhenTheProviderIsAlsoConfigured(): void
    {
        // Deliberately configured out of dependency order — the kernel must still boot the
        // provider before the dependent, per ContractResolver::getLoadOrder().
        $kernel = Kernel::boot(['plugins' => [DependentPlugin::class, ProvidingPlugin::class]]);

        $this->assertSame(['ProvidingPlugin', 'DependentPlugin'], $kernel->bootedPluginNames());
        $this->assertSame([DependentPlugin::class], DependentPlugin::$bootOrder);
    }

    public function testAPluginBootingListenerCanVetoAPluginsBootViaInterceptionSlot(): void
    {
        $dispatcher = new EventDispatcher(new NullLogger());
        $dispatcher->subscribe('plugin.booting', static function (string $event, array $payload): void {
            $slot = $payload['slot'];
            \assert($slot instanceof InterceptionSlot);
            $slot->stop();
        });

        $bootedEventFired = false;
        $dispatcher->subscribe('plugin.booted', static function () use (&$bootedEventFired): void {
            $bootedEventFired = true;
        });

        $kernel = Kernel::boot(['plugins' => [VetoingSubscriberPlugin::class], 'dispatcher' => $dispatcher]);

        $this->assertFalse(VetoingSubscriberPlugin::$booted, 'boot() must never run for a vetoed plugin');
        $this->assertFalse($bootedEventFired, 'plugin.booted must never fire for a vetoed plugin');
        $this->assertSame([], $kernel->bootedPluginNames());
        $this->assertCount(1, $kernel->plugins(), 'the vetoed plugin is still instantiated and tracked');
    }

    public function testLifecycleEventsFireInTheDocumentedOrder(): void
    {
        $dispatcher = new EventDispatcher(new NullLogger());
        $fired = [];
        foreach (['capability.resolved', 'plugin.booting', 'plugin.booted', 'kernel.booted'] as $eventName) {
            $dispatcher->subscribe($eventName, static function (string $event) use (&$fired): void {
                $fired[] = $event;
            });
        }

        Kernel::boot(['plugins' => [ProvidingPlugin::class], 'dispatcher' => $dispatcher]);

        $this->assertSame(
            ['capability.resolved', 'plugin.booting', 'plugin.booted', 'kernel.booted'],
            $fired,
        );
    }

    public function testLifecycleEventsCarryTheirDocumentedValueObjects(): void
    {
        $dispatcher = new EventDispatcher(new NullLogger());
        $events = [];
        foreach (['capability.resolved', 'plugin.booting', 'plugin.booted', 'kernel.booted'] as $eventName) {
            $dispatcher->subscribe($eventName, static function (string $event, array $payload) use (&$events): void {
                $events[$event] = $payload['event'];
            });
        }

        Kernel::boot(['plugins' => [ProvidingPlugin::class], 'dispatcher' => $dispatcher]);

        $this->assertInstanceOf(CapabilityResolvedEvent::class, $events['capability.resolved']);
        $this->assertInstanceOf(PluginBootingEvent::class, $events['plugin.booting']);
        $this->assertSame('ProvidingPlugin', $events['plugin.booting']->pluginName);
        $this->assertInstanceOf(PluginBootedEvent::class, $events['plugin.booted']);
        $this->assertInstanceOf(KernelBootedEvent::class, $events['kernel.booted']);
        $this->assertSame(['ProvidingPlugin'], $events['kernel.booted']->bootedPluginNames);
    }

    public function testAnInjectedContainerIsReusedRatherThanReplaced(): void
    {
        $container = new DIContainer();
        $kernel = Kernel::boot(['container' => $container, 'plugins' => [ProvidingPlugin::class]]);

        $this->assertSame($container, $kernel->container());
    }

    public function testToolRegistryWiringIsOptInAndNeverConstructedByTheKernel(): void
    {
        $registry = new RecordingToolRegistry();

        $kernel = Kernel::boot([
            'plugins' => [ToolProvidingPlugin::class],
            'toolRegistry' => $registry,
        ]);

        $this->assertSame($registry, $kernel->toolRegistry());
        $this->assertSame(['fixture_tool'], $registry->registeredNames);
    }

    public function testWithNoToolRegistryConfiguredNoToolIsEverRegistered(): void
    {
        // ToolProvidingPlugin::registerTools() would throw if ever called with no registry —
        // it isn't, because boot() without 'toolRegistry' never calls it. This is the "do NOT
        // hard-depend on tool-runtime" contract exercised end to end.
        $kernel = Kernel::boot(['plugins' => [ToolProvidingPlugin::class]]);

        $this->assertNull($kernel->toolRegistry());
    }

    public function testACommandProvidingPluginsCommandsAreDiscoveredAndExposedViaTheKernel(): void
    {
        $kernel = Kernel::boot(['plugins' => [CommandProvidingPlugin::class]]);

        $commands = $kernel->commands();
        $this->assertCount(1, $commands);
        $this->assertSame('fixture:greet', $commands[0]->name);
        $this->assertSame('A fixture command that returns a greeting.', $commands[0]->description);
        $this->assertIsCallable($commands[0]->handler);
        $this->assertSame('hello from fixture:greet', ($commands[0]->handler)());
        $this->assertNull($commands[0]->inputSchema);
    }

    public function testAPluginWithoutCommandProviderInterfaceContributesNoCommands(): void
    {
        $kernel = Kernel::boot(['plugins' => [ProvidingPlugin::class]]);

        $this->assertSame([], $kernel->commands());
    }

    public function testAnOperationProvidingPluginsOperationsAreDiscoveredViaTheKernel(): void
    {
        $kernel = Kernel::boot(['plugins' => [OperationProvidingPlugin::class]]);

        $commands = $kernel->commands();
        $this->assertCount(1, $commands);
        $this->assertSame('fixture:op', $commands[0]->name);
        $this->assertTrue($commands[0]->mutating);
        $this->assertSame(['fixture:write'], $commands[0]->scopes);
        $this->assertSame('from operation', ($commands[0]->handler)());
    }
}
