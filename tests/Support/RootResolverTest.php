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

    // ---- the fallback for a process Composer does not manage --------------------

    /**
     * Runs `$code` in a child PHP process bootstrapped with an autoloader that
     * knows only this package — no Composer autoloader, so
     * `Composer\InstalledVersions` genuinely does not exist there.
     *
     * The third tier of the resolution order exists for exactly that process: a
     * PHAR, a classmap-only autoloader, this package used outside Composer at
     * all. It cannot be reached from a suite Composer itself is running, which
     * is why it had never been executed.
     */
    private function withoutComposer(string $code, string $cwd): string
    {
        $bootstrap = \dirname(__DIR__) . '/Fixtures/autoload-without-composer.php';
        $script = sys_get_temp_dir() . '/milpa-runtime-sin-composer-' . uniqid('', true) . '.php';

        file_put_contents($script, '<?php require ' . var_export($bootstrap, true) . '; ' . $code);

        try {
            return trim((string) shell_exec(
                'cd ' . escapeshellarg($cwd) . ' && php ' . escapeshellarg($script) . ' 2>&1'
            ));
        } finally {
            @unlink($script);
        }
    }

    public function testWithoutComposerItWalksUpFromTheCwdToTheNearestComposerJson(): void
    {
        // Started two directories deep inside the package: the walk has to climb
        // past both and stop at the composer.json, not at the first directory
        // that happens to exist.
        $salida = $this->withoutComposer(
            'echo (new Milpa\Runtime\Support\RootResolver())->resolve();',
            \dirname(__DIR__, 2) . '/src/Support',
        );

        self::assertSame(\dirname(__DIR__, 2), $salida);
    }

    public function testWithoutComposerAndNoComposerJsonAnywhereAboveItSaysSoLoudly(): void
    {
        // Nothing to find all the way up to `/`. A plausible-looking wrong path
        // here is the exact failure this class was written to replace, so the
        // walk has to run out and throw rather than answer.
        $salida = $this->withoutComposer(
            'try { (new Milpa\Runtime\Support\RootResolver())->resolve(); echo "SIN ERROR"; }'
            . ' catch (Milpa\Runtime\Support\RootNotFoundException $e) { echo "LANZO: " . $e->getMessage(); }',
            '/',
        );

        self::assertStringStartsWith('LANZO:', $salida);
        self::assertStringContainsString('no explicit root was given', $salida);
    }

    public function testWithoutComposerAnExplicitRootStillWinsBeforeAnyWalking(): void
    {
        $esperado = \dirname(__DIR__, 2) . '/src';

        $salida = $this->withoutComposer(
            'echo (new Milpa\Runtime\Support\RootResolver(' . var_export($esperado, true) . '))->resolve();',
            '/',
        );

        self::assertSame($esperado, $salida);
    }
}
