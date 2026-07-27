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

namespace Milpa\Runtime\Boot;

use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Event\MilpaEventDispatcherInterface;
use Milpa\Interfaces\Tooling\ToolRegistryInterface;

/**
 * Everything {@see \Milpa\Runtime\Kernel::boot()} has already wired by the time the
 * plugin-boot phase starts — handed to the strategy as one immutable value.
 *
 * `config` is the raw `$config` array `boot()` received, verbatim: the inline strategy
 * reads `plugins`/`hostProfile`/`evaluatedAt`/`name` from it; custom strategies may read
 * their own keys without the kernel having to know them.
 */
final readonly class BootContext
{
    /** @param array<string, mixed> $config */
    public function __construct(
        public DIContainerInterface $container,
        public MilpaEventDispatcherInterface $dispatcher,
        public string $root,
        public array $config,
        public ?ToolRegistryInterface $toolRegistry,
    ) {
    }
}
