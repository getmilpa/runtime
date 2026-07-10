<?php

declare(strict_types=1);

namespace Milpa\Runtime;

use Milpa\Command\Operation;

/**
 * @deprecated Use {@see \Milpa\Command\Operation} directly. Retained as a backward-compatible
 *             subclass so plugins and hosts compiled against `Milpa\Runtime\CommandDefinition`
 *             keep working: it inherits `Operation`'s constructor and its public
 *             `name`/`description`/`handler`/`inputSchema` properties unchanged, and every
 *             `CommandDefinition` is-an `Operation`, so `Kernel::commands()` (now
 *             `list<Operation>`) accepts both.
 */
final readonly class CommandDefinition extends Operation
{
}
