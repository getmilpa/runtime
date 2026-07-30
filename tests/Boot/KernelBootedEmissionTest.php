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

namespace Milpa\Runtime\Tests\Boot;

use Milpa\Events\KernelBootedEvent;
use Milpa\Eventing\EventDispatcher;
use Milpa\Runtime\Boot\BootContext;
use Milpa\Runtime\Boot\PluginBootResult;
use Milpa\Runtime\Boot\PluginBootStrategyInterface;
use Milpa\Runtime\Kernel;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class KernelBootedEmissionTest extends TestCase
{
    public function testStrategyThatAlreadyEmittedSuppressesTheKernelDispatch(): void
    {
        $dispatcher = new EventDispatcher(new NullLogger());
        $received = 0;
        $dispatcher->subscribe('kernel.booted', function () use (&$received): void {
            ++$received;
        });

        $strategy = new class () implements PluginBootStrategyInterface {
            public function bootPlugins(BootContext $context): PluginBootResult
            {
                // Simulate a strategy that ALREADY emitted (as PluginsManager does inside loadPlugins()).
                $context->dispatcher->dispatch('kernel.booted', ['event' => new KernelBootedEvent(['fake'])]);

                return new PluginBootResult(
                    plugins: [],
                    bootedPluginNames: ['fake'],
                    emittedKernelBooted: true,
                );
            }
        };

        Kernel::boot(['root' => sys_get_temp_dir(), 'dispatcher' => $dispatcher, 'pluginBoot' => $strategy]);

        self::assertSame(1, $received, 'kernel.booted must be emitted exactly ONCE when the strategy already emitted it');
    }

    public function testDefaultKeepsTheKernelEmission(): void
    {
        $dispatcher = new EventDispatcher(new NullLogger());
        $received = 0;
        $dispatcher->subscribe('kernel.booted', function () use (&$received): void {
            ++$received;
        });

        Kernel::boot(['root' => sys_get_temp_dir(), 'dispatcher' => $dispatcher]);

        self::assertSame(1, $received, 'the default (inline, no flag) emits exactly once, as always');
    }
}
