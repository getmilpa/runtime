<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;
use Milpa\Interfaces\Tooling\ToolProviderInterface;
use Milpa\Interfaces\Tooling\ToolRegistryInterface;

/** A plugin that registers one tool when the kernel wires a tool registry — and only then. */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'ToolProvidingPlugin',
    type: 'Service',
)]
final class ToolProvidingPlugin implements PluginInterface, ToolProviderInterface
{
    public function __construct(private readonly DIContainerInterface $container)
    {
    }

    public function boot(): void
    {
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

    public function registerTools(ToolRegistryInterface $registry): void
    {
        $registry->register('fixture_tool', 'A fixture tool.', [], static fn (): array => []);
    }

    /** @return array<string> */
    public function getPromptSections(): array
    {
        return [];
    }
}
