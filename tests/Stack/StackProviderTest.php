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

namespace Milpa\Runtime\Tests\Stack;

use Milpa\Runtime\Kernel;
use Milpa\Runtime\Stack\ServiceDeclaration;
use Milpa\Runtime\Stack\StackProviderInterface;
use Milpa\Runtime\Tests\Fixtures\Http\RoutedPlugin;
use Milpa\Runtime\Tests\Fixtures\Stack\HubPlugin;
use PHPUnit\Framework\TestCase;

final class StackProviderTest extends TestCase
{
    public function testABootedProviderIsFoundByInstanceofAndDeclaresItsServices(): void
    {
        $kernel = Kernel::boot(['root' => sys_get_temp_dir(), 'plugins' => [RoutedPlugin::class, HubPlugin::class]]);

        $services = [];
        foreach ($kernel->plugins() as $plugin) {
            if ($plugin instanceof StackProviderInterface) {
                array_push($services, ...$plugin->services());
            }
        }

        self::assertCount(1, $services, 'only the provider declares; the routed plugin is ignored');
        self::assertInstanceOf(ServiceDeclaration::class, $services[0]);
        self::assertSame('hub', $services[0]->name);
        self::assertSame('example/hub:1', $services[0]->image);
        self::assertSame(3000, $services[0]->probePort());
        self::assertSame('HUB_JWT_KEY', $services[0]->env[1]->name);
        self::assertTrue($services[0]->env[1]->secret);
    }
}
