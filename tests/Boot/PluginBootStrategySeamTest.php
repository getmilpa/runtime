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

use Milpa\Runtime\Boot\BootContext;
use Milpa\Runtime\Boot\InlinePluginBootStrategy;
use Milpa\Runtime\Boot\PluginBootResult;
use Milpa\Runtime\Boot\PluginBootStrategyInterface;
use Milpa\Runtime\Kernel;
use PHPUnit\Framework\TestCase;

final class PluginBootStrategySeamTest extends TestCase
{
    public function testBootHonorsInjectedStrategy(): void
    {
        $seen = null;
        $strategy = new class($seen) implements PluginBootStrategyInterface {
            public function __construct(private mixed &$seen)
            {
            }

            public function bootPlugins(BootContext $context): PluginBootResult
            {
                $this->seen = $context;

                return new PluginBootResult(
                    plugins: [],
                    bootedPluginNames: ['fake-plugin'],
                );
            }
        };

        $kernel = Kernel::boot([
            'root' => sys_get_temp_dir(),
            'pluginBoot' => $strategy,
        ]);

        self::assertSame(['fake-plugin'], $kernel->bootedPluginNames());
        self::assertInstanceOf(BootContext::class, $seen);
        self::assertSame(sys_get_temp_dir(), $seen->root);
        self::assertNull($seen->toolRegistry);
    }

    public function testDefaultStrategyIsTheInlineOne(): void
    {
        // Sin pluginBoot y sin plugins: el default (inline) bootea vacío sin tronar,
        // exactamente como boot() se comportaba antes del seam.
        $kernel = Kernel::boot(['root' => sys_get_temp_dir()]);

        self::assertSame([], $kernel->bootedPluginNames());
        self::assertSame([], $kernel->plugins());
    }

    public function testResultRoutesFeedTheRouter(): void
    {
        $strategy = new class implements PluginBootStrategyInterface {
            public function bootPlugins(BootContext $context): PluginBootResult
            {
                return new PluginBootResult(plugins: [], bootedPluginNames: []);
            }
        };

        $kernel = Kernel::boot(['root' => sys_get_temp_dir(), 'pluginBoot' => $strategy]);

        // Router vacío pero construido — el contrato post-fase no cambió.
        self::assertInstanceOf(\Milpa\Http\Routing\Router::class, $kernel->router());
    }

    public function testInlineStrategyIsInstantiableDirectly(): void
    {
        self::assertInstanceOf(PluginBootStrategyInterface::class, new InlinePluginBootStrategy());
    }
}
