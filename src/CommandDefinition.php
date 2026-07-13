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

namespace Milpa\Runtime;

use Milpa\Command\Operation;

/**
 * A backward-compatible alias of the {@see \Milpa\Command\Operation} atom.
 *
 * @deprecated Use {@see \Milpa\Command\Operation} directly. Retained as a subclass so plugins and
 *             hosts compiled against `Milpa\Runtime\CommandDefinition` keep working: it inherits
 *             `Operation`'s constructor and its public `name`/`description`/`handler`/`inputSchema`
 *             properties unchanged, and every `CommandDefinition` is-an `Operation`, so
 *             `Kernel::commands()` (now `list<Operation>`) accepts both.
 */
final readonly class CommandDefinition extends Operation
{
}
