<?php

declare(strict_types=1);

/**
 * An autoloader for this package's own namespace and nothing else.
 *
 * A child process bootstrapped with this file has no Composer autoloader, so
 * `Composer\InstalledVersions` genuinely does not exist there. That is the only
 * way to reach RootResolver's third tier — the walk up from the cwd — which
 * exists for exactly that process (a PHAR, a classmap-only autoloader, this
 * package used outside Composer at all) and cannot be reached from a suite
 * Composer itself is running.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Milpa\\Runtime\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = \dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
