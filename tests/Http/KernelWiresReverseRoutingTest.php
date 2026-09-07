<?php

/**
 * This file is part of Milpa Runtime — the kernel and HTTP runtime of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/runtime
 */

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Http;

use Milpa\Http\Routing\UrlGeneratorInterface;
use Milpa\Runtime\Kernel;
use Milpa\Runtime\Tests\Fixtures\Http\RoutedPlugin;
use PHPUnit\Framework\TestCase;

/**
 * A booted app can build the URL of its own route, without knowing the path.
 *
 * Reverse routing is the KERNEL'S to wire: the route table is assembled here, from every plugin, and a
 * generator built anywhere else would be reading a copy. Before this, `UrlGeneratorInterface` had no
 * implementation at all and every URL in the framework was a string literal — `/webauthn/signin` alone
 * appears seven times, three of them as separate constants in three packages (greenhouse
 * decisions/0215, F4).
 */
final class KernelWiresReverseRoutingTest extends TestCase
{
    public function testABootedAppCanGenerateTheUrlOfItsOwnRoute(): void
    {
        $kernel = Kernel::boot(['plugins' => [RoutedPlugin::class]]);

        $generator = $kernel->container()->get(UrlGeneratorInterface::class);

        self::assertInstanceOf(UrlGeneratorInterface::class, $generator);
        self::assertSame('/hello/milpa', $generator->generate('hello', ['name' => 'milpa']));
    }

    public function testTheGeneratorSeesEXACTLYTheTableTheRouterMatchesAgainst(): void
    {
        // One table, not two: the generator is built FROM the router the kernel keeps, so a route that
        // matches is a route that generates and there is nothing to drift.
        $kernel = Kernel::boot(['plugins' => [RoutedPlugin::class]]);

        $paths = array_map(static fn (object $route): string => $route->path, $kernel->router()->routes());

        self::assertContains('/hello/{name}', $paths);
    }

    public function testAnAppWithNoRoutesStillGetsAGenerator(): void
    {
        // It answers «no such route» rather than not existing — a consumer type-hints the contract and
        // does not have to ask whether this app happens to have routes.
        $kernel = Kernel::boot(['plugins' => []]);

        self::assertInstanceOf(UrlGeneratorInterface::class, $kernel->container()->get(UrlGeneratorInterface::class));
    }
}
