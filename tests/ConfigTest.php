<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests;

use Milpa\Runtime\Config;
use Milpa\Runtime\Kernel;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDotAccessWalksNestedArrays(): void
    {
        $c = new Config(['storage' => ['path' => '/var/x'], 'flat' => 1]);
        self::assertSame('/var/x', $c->get('storage.path'));
        self::assertSame(1, $c->get('flat'));
        self::assertNull($c->get('storage.missing'));
        self::assertSame('d', $c->get('a.b.c', 'd'));
        self::assertTrue($c->has('storage.path'));
        self::assertFalse($c->has('storage.nope'));
    }

    public function testKernelRegistersConfigBagForPluginsToRead(): void
    {
        $kernel = Kernel::boot(['plugins' => [], 'config' => ['storage' => ['path' => '/tmp/posts.json']], 'root' => \dirname(__DIR__)]);
        $cfg = $kernel->container()->get(Config::class);
        self::assertInstanceOf(Config::class, $cfg);
        self::assertSame('/tmp/posts.json', $cfg->get('storage.path'));
    }
}
