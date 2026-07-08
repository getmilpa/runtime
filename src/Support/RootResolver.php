<?php

declare(strict_types=1);

namespace Milpa\Runtime\Support;

use Composer\InstalledVersions;

/**
 * Resolves the Milpa HOST APPLICATION's filesystem root — the directory holding its
 * `composer.json`, `plugins/`, `config/`, etc. — the one piece of topology
 * {@see \Milpa\Runtime\Kernel::boot()} needs and cannot safely assume about its own location.
 *
 * Ported from `milpa/devtools`' `Milpa\DevTools\Support\RootResolver` (same resolution order,
 * same failure mode) rather than depended on: `milpa/runtime` boots applications, it does not
 * scaffold/validate them, so pulling in the devtools package — and its own Doctrine dependency —
 * as a runtime dependency of every booted app would be exactly the kind of ambient coupling this
 * front exists to kill. See the front's report for the tradeoff.
 *
 * Resolution order (first hit wins):
 *   1. an explicit root passed to the constructor — host wiring always wins (e.g. `Kernel::boot(['root' => ...])`
 *      or a test fixture);
 *   2. `Composer\InstalledVersions::getRootPackage()['install_path']` — the Composer-canonical answer
 *      to "where is the application that required me", correct regardless of install depth or path-repo
 *      vs. registry install, valid the instant Composer's generated autoloader is on the include path
 *      (which it always is for any Composer-managed PHP process — this is not an optional dependency,
 *      see `composer-runtime-api` in composer.json);
 *   3. walk up from `getcwd()` looking for the nearest ancestor `composer.json` — a last-resort
 *      fallback for the pathological case where Composer's own runtime API is unavailable (e.g. this
 *      package used outside a Composer-managed process entirely).
 *
 * Throws {@see RootNotFoundException} instead of returning a plausible-looking wrong path when none of
 * the three resolves — callers get an honest, loud failure instead of a silently wrong `rootPath`
 * global (the exact failure mode this class replaces).
 */
final class RootResolver
{
    public function __construct(private readonly ?string $explicitRoot = null)
    {
    }

    /** Resolves the host application's root directory per the three-tier order documented above. */
    public function resolve(): string
    {
        if ($this->explicitRoot !== null) {
            return $this->realOrFail($this->explicitRoot, 'explicit root');
        }

        $viaComposer = $this->fromInstalledVersions();
        if ($viaComposer !== null) {
            return $viaComposer;
        }

        $viaWalk = $this->fromCwdWalk();
        if ($viaWalk !== null) {
            return $viaWalk;
        }

        throw new RootNotFoundException(
            'could not resolve the Milpa host application root: no explicit root was given, '
            . 'Composer\\InstalledVersions is unavailable or reports no root package install_path, '
            . 'and no composer.json was found walking up from ' . (getcwd() ?: '(unknown cwd)'),
        );
    }

    private function fromInstalledVersions(): ?string
    {
        if (!class_exists(InstalledVersions::class)) {
            return null;
        }

        $root = InstalledVersions::getRootPackage()['install_path'];
        if ($root === '') {
            return null;
        }

        $real = realpath($root);

        return $real !== false ? $real : null;
    }

    private function fromCwdWalk(): ?string
    {
        $dir = getcwd();
        if ($dir === false) {
            return null;
        }

        while (true) {
            if (is_file($dir . '/composer.json')) {
                return $dir;
            }
            $parent = \dirname($dir);
            if ($parent === $dir) {
                return null;
            }
            $dir = $parent;
        }
    }

    private function realOrFail(string $path, string $source): string
    {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            throw new RootNotFoundException("{$source} '{$path}' does not resolve to a real directory");
        }

        return $real;
    }
}
