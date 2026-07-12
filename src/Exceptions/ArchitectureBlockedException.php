<?php

declare(strict_types=1);

namespace Milpa\Runtime\Exceptions;

use Milpa\Exceptions\Plugin\PluginDependencyException;
use Milpa\Resolver\Report\ResolutionReport;

/**
 * Thrown by {@see \Milpa\Runtime\Kernel::boot()} when the architecture graph resolves to `blocked`:
 * the typed pre-boot failure that carries the resolver's WHOLE {@see ResolutionReport} — every
 * learnable error, conflict, miss and the partial `loadOrder` — not just the one-line message.
 *
 * Narrowing BC: it extends {@see PluginDependencyException} (the exception type the retired
 * `CapabilityGraphChecker` threw and runtime 0.3 kept), so every existing
 * `catch (PluginDependencyException)` — and every `catch (\RuntimeException)` above it — keeps
 * working, while a 0.4-aware caller catches this type and reads `->report` directly instead of
 * parsing the message.
 */
final class ArchitectureBlockedException extends PluginDependencyException
{
    /**
     * Builds the typed pre-boot failure: the blocked report rides on the exception verbatim.
     *
     * @param ResolutionReport $report  The full blocked report the architecture gate produced.
     * @param string           $message The learnable one-line diagnosis — the report's own
     *                                  `firstLearnableLine()`, or the kernel's fallback when the
     *                                  report carries no errors.
     */
    public function __construct(
        public readonly ResolutionReport $report,
        string $message,
    ) {
        parent::__construct($message);
    }
}
