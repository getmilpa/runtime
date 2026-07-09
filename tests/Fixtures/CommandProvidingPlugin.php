<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;
use Milpa\Runtime\CommandDefinition;
use Milpa\Runtime\CommandProviderInterface;

/** A plugin that contributes a single trivial command to the kernel's command table. */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'CommandProvidingPlugin',
    type: 'Service',
)]
final class CommandProvidingPlugin implements PluginInterface, CommandProviderInterface
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

    /** @return list<CommandDefinition> */
    public function commands(): array
    {
        return [
            new CommandDefinition(
                name: 'fixture:greet',
                description: 'A fixture command that returns a greeting.',
                handler: static fn (): string => 'hello from fixture:greet',
            ),
        ];
    }
}
