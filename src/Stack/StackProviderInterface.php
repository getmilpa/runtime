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

namespace Milpa\Runtime\Stack;

/**
 * Declares that a plugin needs backing services — containers the host has to run for the plugin to work.
 *
 * The stack counterpart of {@see \Milpa\Runtime\Http\RouteProviderInterface}: a plugin that needs a message
 * hub, a database or a cache says so HERE, as data, instead of leaving it to a README. Whoever operates
 * the host (an admin panel, a CLI, the agent) discovers implementers by `instanceof` over the booted
 * plugins and reads what they need — image, ports, environment — to show it, project it to a compose
 * file, or check that it is reachable. The runtime does not start anything: declaring is the seam,
 * running is the operator's call (greenhouse decisions/0201).
 */
interface StackProviderInterface
{
    /**
     * The services this plugin needs the host to run.
     *
     * @return list<ServiceDeclaration>
     */
    public function services(): array;
}
