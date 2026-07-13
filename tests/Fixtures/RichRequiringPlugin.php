<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;

/**
 * A plugin whose `requires` entry is a CANONICAL capability RECORD — the second shape
 * `#[PluginMetadata]` sanctions — not a bare FQCN string. Its `id` names {@see TestCapability},
 * the id {@see ProvidingPlugin}'s bare `provides` synthesizes (the incremental migration path: a
 * canonical record consuming a legacy provision), so configured WITH ProvidingPlugin the graph
 * closes — gate AND ordering edge — and this must BOOT after its provider; configured alone it
 * must GATE with the learnable MILPA_CAPABILITY_MISSING. In neither case may the record
 * raw-TypeError `fromInterface()` (the Kernel routes every requires entry through
 * `CapabilityRequirement::parse()`, runtime 0.4.2).
 */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'RichRequiringPlugin',
    type: 'Service',
    requires: [[
        'id' => TestCapability::class,
        'interface' => TestCapability::class,
        'constraint' => '*',
    ]],
)]
final class RichRequiringPlugin implements PluginInterface
{
    public static bool $booted = false;

    public function __construct(private readonly DIContainerInterface $container)
    {
    }

    public function boot(): void
    {
        self::$booted = true;
    }

    public function install(): void
    {
    }

    public function uninstall(): void
    {
    }

    public function enable(): void
    {
    }

    public function disable(): void
    {
    }
}
