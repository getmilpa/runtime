<?php

/*
 * This file is part of milpa/runtime.
 *
 * (c) TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Milpa\Runtime;

/**
 * Read-only application configuration bag, resolved from `Kernel::boot()`'s `config` key and
 * registered in the container as `Config::class`.
 *
 * This is the seam plugins use to receive configuration WITHOUT constructor arguments: the
 * {@see \Milpa\Interfaces\Plugin\PluginInterface} contract fixes the constructor to
 * `(DIContainerInterface $container)`, so a plugin that needs a value (a storage path, an API
 * base URL) reads it here in `boot()` — `$container->get(Config::class)->get('storage.path')` —
 * rather than through an env var or a widened constructor. Dot-notation keys index nested arrays.
 */
final readonly class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values = [])
    {
    }

    /**
     * Read a value by dot-notation key (`'storage.path'` walks `$values['storage']['path']`).
     * Returns `$default` if any segment is missing or not an array mid-path.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cursor = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /** True if the dot-notation key resolves to a set value (even `null`). */
    public function has(string $key): bool
    {
        $sentinel = new \stdClass();

        return $this->get($key, $sentinel) !== $sentinel;
    }

    /** @return array<string, mixed> The whole bag. */
    public function all(): array
    {
        return $this->values;
    }
}
