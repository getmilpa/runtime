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
 * Asks the program on a port the one question a {@see ServiceSignature} names — the seam between «the
 * port accepts» ({@see ReachabilityProbe}) and «the service is the one declared», so a test can fake the
 * answer and the real one ({@see HttpSignatureProbe}) stays one loopback request with a short timeout.
 */
interface SignatureProbe
{
    /** The HTTP status the program on the port answered the signature's request with; null when no HTTP answer came. */
    public function answer(int $port, ServiceSignature $signature): ?int;
}
