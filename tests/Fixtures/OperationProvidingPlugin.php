<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Attributes\PluginMetadata;
use Milpa\Command\CommandProvider;
use Milpa\Command\Operation;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;

/** A plugin that contributes a single trivial operation to the kernel's command table. */
#[PluginMetadata(
    version: '1.0.0',
    author: 'Milpa Runtime Tests',
    site: 'https://example.test',
    name: 'OperationProvidingPlugin',
    type: 'Service',
)]
final class OperationProvidingPlugin implements PluginInterface, CommandProvider
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

    /** @return list<Operation> */
    public function operations(): array
    {
        return [
            new Operation(
                name: 'fixture:op',
                description: 'A fixture operation.',
                handler: static fn (): string => 'from operation',
                mutating: true,
                scopes: ['fixture:write'],
            ),
        ];
    }
}
