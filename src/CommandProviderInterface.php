<?php

declare(strict_types=1);

namespace Milpa\Runtime;

/**
 * Declares that a plugin contributes commands to the kernel's command table — the discovery seam
 * for Command-as-atom's CLI surface (see `docs/library/vision-milpa-commands.md`).
 *
 * This is the command counterpart of {@see \Milpa\Runtime\Http\RouteProviderInterface}:
 * {@see Kernel::boot()} checks every successfully booted plugin for this interface and merges its
 * {@see commands()} into the {@see Kernel::commands()} list it exposes — the same
 * "instanceof, then auto-wire" pattern the family already uses for routes and tools, applied to
 * the new {@see CommandDefinition} atom. A host CLI (e.g. `milpa/skeleton`'s `coa`) registers
 * every discovered command as a subcommand instead of hardcoding them, and a plugin can therefore
 * add its own `coa` command with zero host-file edits.
 */
interface CommandProviderInterface
{
    /**
     * Commands this plugin contributes to the kernel's command table.
     *
     * @return list<CommandDefinition>
     */
    public function commands(): array;
}
