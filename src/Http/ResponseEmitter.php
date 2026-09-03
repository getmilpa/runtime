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

namespace Milpa\Runtime\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Emits a PSR-7 response to the SAPI, streaming it when the body is a {@see CallbackStream} (greenhouse evidence/0472).
 *
 * A front controller used to hand-roll `http_response_code(...)`, a `header()` loop, and
 * `echo $response->getBody()`. That last step materializes the whole body — fine for a page, fatal for a
 * live feed, because nothing reaches the client until the response is complete. This emitter replaces that
 * tail: it sends the status and headers, then, if the body is a {@see CallbackStream}, runs the callback
 * (which writes and flushes over the life of the connection) INSTEAD of stringifying it. Every ordinary
 * response takes the same buffered `echo (string) $body` path as before, so adopting the emitter is behavior
 * -preserving; streaming is the new capability a `CallbackStream` body opts into.
 *
 * The status and header sinks — and, for streaming, the buffer flusher — are injectable so emission is
 * testable without a live SAPI (the defaults are PHP's own `http_response_code()` and `header()`, matching
 * the front controller this replaces — multiple values of one header are appended, not replaced).
 *
 * Streaming a {@see CallbackStream} first defeats PHP's output buffering: without ending any active output
 * buffer, `flush()` inside the callback cannot push bytes to the client, so the "stream" would arrive whole
 * at connection close — no streaming at all. Ending output buffers is a SAPI-emission concern, so it lives
 * here (not in every streaming handler), and it is injectable so a test can capture the streamed bytes in
 * its own buffer instead of having them flushed away.
 */
final class ResponseEmitter
{
    /** @var callable(int): void */
    private $statusSink;

    /** @var callable(string): void */
    private $headerSink;

    /** @var callable(): void */
    private $bufferFlusher;

    /**
     * @param (callable(int): void)|null    $statusSink    Receives the status code (default: `http_response_code`).
     * @param (callable(string): void)|null $headerSink    Receives each `"Name: value"` line (default: `header(..., false)`).
     * @param (callable(): void)|null       $bufferFlusher Ends active output buffers before streaming (default: `ob_end_flush` down to level 0); a test passes a no-op to keep its capture buffer.
     */
    public function __construct(?callable $statusSink = null, ?callable $headerSink = null, ?callable $bufferFlusher = null)
    {
        $this->statusSink = $statusSink ?? static fn (int $code): mixed => http_response_code($code);
        $this->headerSink = $headerSink ?? static function (string $line): void {
            header($line, false);
        };
        $this->bufferFlusher = $bufferFlusher ?? static function (): void {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
        };
    }

    /** Send the response: status, headers, then the body — streamed if it is a {@see CallbackStream}. */
    public function emit(ResponseInterface $response): void
    {
        ($this->statusSink)($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                ($this->headerSink)("{$name}: {$value}");
            }
        }

        $body = $response->getBody();
        if ($body instanceof CallbackStream) {
            // Defeat output buffering so the callback's own flush() reaches the client incrementally.
            ($this->bufferFlusher)();
            ($body->callback())();

            return;
        }

        echo (string) $body;
    }
}
