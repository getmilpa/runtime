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
 * The real signature probe: one `GET <path> HTTP/1.0` to the loopback host, reading the status line only.
 *
 * It reads the STATUS LINE and closes: a service whose right answer is a stream (a hub subscription) must
 * not hold the reader, and the status is what a protocol obliges. Anything that is not an HTTP status line
 * within the timeout — a refused connect, a program that speaks another protocol, silence — is `null`,
 * never an exception: a reader reports it, it does not raise it.
 */
final class HttpSignatureProbe implements SignatureProbe
{
    public const TIMEOUT_SECONDS = 0.5;

    /** @param string $host the host to ask — the same loopback address the reachability probe tried */
    public function __construct(private readonly string $host = TcpProbe::HOST)
    {
    }

    /** The status the program on the port answered `GET <path>` with, or null when no HTTP status line came back. */
    public function answer(int $port, ServiceSignature $signature): ?int
    {
        $errno = 0;
        $error = '';
        $socket = @fsockopen($this->host, $port, $errno, $error, self::TIMEOUT_SECONDS);
        if ($socket === false) {
            return null;
        }
        try {
            stream_set_timeout($socket, 0, (int) (self::TIMEOUT_SECONDS * 1_000_000));
            $request = 'GET ' . $signature->path . " HTTP/1.0\r\nHost: " . $this->host . ':' . $port . "\r\nConnection: close\r\n\r\n";
            if (@fwrite($socket, $request) === false) {
                return null;
            }
            $line = fgets($socket, 256);
        } finally {
            fclose($socket);
        }
        if (!\is_string($line) || preg_match('#^HTTP/\d(?:\.\d)? (\d{3})\b#', $line, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }
}
