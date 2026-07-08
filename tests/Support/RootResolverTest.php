<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Support;

use Milpa\Runtime\Support\RootNotFoundException;
use Milpa\Runtime\Support\RootResolver;
use PHPUnit\Framework\TestCase;

final class RootResolverTest extends TestCase
{
    public function testAnExplicitRootWinsOverEveryOtherSource(): void
    {
        $resolver = new RootResolver(__DIR__);

        $this->assertSame(realpath(__DIR__), $resolver->resolve());
    }

    public function testAnExplicitRootThatDoesNotExistThrows(): void
    {
        $resolver = new RootResolver('/this/path/does/not/exist/on/any/machine');

        $this->expectException(RootNotFoundException::class);

        $resolver->resolve();
    }

    public function testWithNoExplicitRootItResolvesViaComposerOrTheCwdWalk(): void
    {
        // No explicit root: under `composer test`/PHPUnit this process IS Composer-managed, so
        // either InstalledVersions or the cwd-walk finds this package's own composer.json.
        $resolver = new RootResolver();

        $root = $resolver->resolve();

        $this->assertDirectoryExists($root);
        $this->assertFileExists($root . '/composer.json');
    }
}
