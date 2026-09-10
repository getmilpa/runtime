<?php

/**
 * This file is part of milpa/runtime — the Milpa PHP framework's runtime.
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
 * Answers whether something on this host accepts a connection on a port — the one measurement anything
 * reading the declared stack makes.
 *
 * A declared service is data; whether it is RUNNING is a fact a reader can only observe. The probe is
 * the seam between the two, so a test can fake the answer and the real one ({@see TcpProbe}) stays a
 * loopback connect with a short timeout — no Docker, no orchestration (greenhouse decisions/0201).
 */
interface ReachabilityProbe
{
    /** True when a TCP connection to the port on loopback succeeds within the probe's timeout. */
    public function reachable(int $port): bool;

    /** The host the probe connects to — what a reader reports next to the port it tried. */
    public function host(): string;
}
