<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests;

use Milpa\Exceptions\Plugin\PluginDependencyException;
use Milpa\Resolver\Report\ResolutionStatus;
use Milpa\Runtime\Exceptions\ArchitectureBlockedException;
use Milpa\Runtime\Kernel;
use Milpa\Runtime\Tests\Fixtures\CommandProvidingPlugin;
use Milpa\Runtime\Tests\Fixtures\CyclePluginA;
use Milpa\Runtime\Tests\Fixtures\CyclePluginB;
use Milpa\Runtime\Tests\Fixtures\DependentPlugin;
use Milpa\Runtime\Tests\Fixtures\DuplicateNamePlugin;
use Milpa\Runtime\Tests\Fixtures\ProvidingPlugin;
use PHPUnit\Framework\TestCase;

/**
 * The boot order comes from the resolver's report (runtime 0.4): `Kernel::boot()` follows the
 * `loadOrder[]` the SAME resolution that gates the architecture computed — one topological pass,
 * not a second sort — and a blocked graph throws the typed {@see ArchitectureBlockedException}
 * carrying the whole report. The untouched {@see KernelBootTest} and {@see KernelArchitectureGateTest}
 * pin that the sequence and the `capability.resolved` payload are byte-identical to 0.3; this suite
 * pins what is NEW: tie-breaking by config order, and the learnable cycle that used to die inside
 * `ContractResolver` as an unlearnable `\RuntimeException` now blocking at the gate.
 */
final class KernelLoadOrderTest extends TestCase
{
    protected function setUp(): void
    {
        ProvidingPlugin::$bootCount = 0;
        DependentPlugin::$bootOrder = [];
        CyclePluginA::$booted = false;
        CyclePluginB::$booted = false;
        DuplicateNamePlugin::$booted = false;
    }

    public function testBootFollowsTheReportsLoadOrderAndTiesKeepTheConfigOrder(): void
    {
        // CommandProvidingPlugin has no edge to anyone: it ties with ProvidingPlugin and must keep
        // its configured position (first), while the provides->requires edge still forces
        // ProvidingPlugin before DependentPlugin even though the config lists the dependent first.
        $kernel = Kernel::boot(['plugins' => [
            DependentPlugin::class,
            CommandProvidingPlugin::class,
            ProvidingPlugin::class,
        ]]);

        $this->assertSame(
            ['CommandProvidingPlugin', 'ProvidingPlugin', 'DependentPlugin'],
            $kernel->bootedPluginNames(),
        );
    }

    public function testATwoPluginCycleThrowsTheTypedExceptionCarryingTheBlockedReport(): void
    {
        try {
            Kernel::boot(['plugins' => [CyclePluginA::class, CyclePluginB::class]]);
            $this->fail('a dependency cycle must block the boot at the architecture gate');
        } catch (ArchitectureBlockedException $e) {
            // Narrowing BC: the typed exception IS a PluginDependencyException, so every existing
            // catch — and the untouched KernelBootTest/KernelArchitectureGateTest — keeps working.
            $this->assertInstanceOf(PluginDependencyException::class, $e);

            // The whole report rides on the exception; the gate only throws when it is blocked.
            $this->assertSame(ResolutionStatus::Blocked, $e->report->status);

            // The message is the report's own learnable first line: code + why + fix + Academy link —
            // not ContractResolver's old, unlearnable 'Circular dependency detected' RuntimeException.
            $this->assertStringContainsString('MILPA_DEPENDENCY_CYCLE', $e->getMessage());
            $this->assertStringContainsString('Learn:', $e->getMessage());
            $this->assertStringContainsString('academy.milpa.lat', $e->getMessage());
            $this->assertStringNotContainsString('Circular dependency detected', $e->getMessage());
        }

        $this->assertFalse(CyclePluginA::$booted, 'boot() must never run once the graph is blocked');
        $this->assertFalse(CyclePluginB::$booted, 'boot() must never run once the graph is blocked');
    }

    public function testTheAttachedReportsLoadOrderExcludesTheCycleMembersButKeepsTheOrderablePlugins(): void
    {
        try {
            Kernel::boot(['plugins' => [ProvidingPlugin::class, CyclePluginA::class, CyclePluginB::class]]);
            $this->fail('a dependency cycle must block the boot even when other plugins are orderable');
        } catch (ArchitectureBlockedException $e) {
            // loadOrder[] holds only what CAN be ordered: the cycle members are excluded (they live
            // in conflicts[] instead), the orderable plugin stays — exactly what the resolver reports.
            $names = array_map(static fn (array $entry): string => (string) ($entry['name'] ?? ''), $e->report->loadOrder);
            $this->assertSame(['ProvidingPlugin'], $names);
            $this->assertNotContains('CyclePluginA', $names);
            $this->assertNotContains('CyclePluginB', $names);
        }

        $this->assertSame(0, ProvidingPlugin::$bootCount, 'a blocked graph boots NOTHING, not even the orderable plugins');
    }

    public function testTwoPluginsSharingAMetadataNameThrowInsteadOfSilentlySkippingOne(): void
    {
        // The resolver keys its graph by metadata name, so two same-named plugins collapse into ONE
        // loadOrder[] entry. Mapping that back onto the two configured records would silently boot
        // only one of them — the defensive contract in Kernel::orderFromReport() throws instead.
        try {
            Kernel::boot(['plugins' => [ProvidingPlugin::class, DuplicateNamePlugin::class]]);
            $this->fail('two plugins sharing a #[PluginMetadata] name must throw, never silently skip one');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('share a #[PluginMetadata] name', $e->getMessage());
        }

        $this->assertSame(0, ProvidingPlugin::$bootCount, 'nothing may boot when the order mapping is refused');
        $this->assertFalse(DuplicateNamePlugin::$booted, 'nothing may boot when the order mapping is refused');
    }
}
