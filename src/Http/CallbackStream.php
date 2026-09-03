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

use Psr\Http\Message\StreamInterface;

/**
 * A response body that is PRODUCED at emit time by a callback, so a handler can stream (greenhouse evidence/0472).
 *
 * The runtime's ordinary emission materializes a body — `echo (string) $body` — which buffers the whole
 * response before a byte reaches the client. That is correct for a normal page and fatal for
 * `text/event-stream`: an SSE feed must write and flush events over the life of the connection. A
 * `CallbackStream` carries a callback INSTEAD of bytes; {@see ResponseEmitter} recognizes it and runs the
 * callback (which writes to the output and flushes) rather than stringifying it. The callback owns the
 * loop and the flushing; this class is only the marker that carries it through the PSR-7 response.
 *
 * It is deliberately not a readable/materializable stream: stringifying it yields an empty string, so an
 * app still on the buffered emission path (not the {@see ResponseEmitter}) serves nothing rather than a
 * broken half-response — a loud "you must emit this with the streaming emitter", not a silent corruption.
 */
final class CallbackStream implements StreamInterface
{
    /** @param \Closure():void $callback Writes the body to the output (echo + flush), owning any loop. */
    public function __construct(private readonly \Closure $callback)
    {
    }

    /** The callback {@see ResponseEmitter} invokes to stream the body. */
    public function callback(): callable
    {
        return $this->callback;
    }

    /** Not materializable: the body only exists when streamed by the emitter. */
    public function __toString(): string
    {
        return '';
    }

    /** No underlying resource to close. */
    public function close(): void
    {
    }

    /**
     * No underlying resource to detach.
     *
     * @return null
     */
    public function detach()
    {
        return null;
    }

    /** Unknown until streamed, so unknown by contract. */
    public function getSize(): ?int
    {
        return null;
    }

    /** Nothing to point into. */
    public function tell(): int
    {
        return 0;
    }

    /** Always at the end: there is no buffer to read from. */
    public function eof(): bool
    {
        return true;
    }

    /** A callback body cannot be rewound. */
    public function isSeekable(): bool
    {
        return false;
    }

    /** Not seekable: the body is produced once, by the emitter. */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new \RuntimeException('A CallbackStream is not seekable; it is streamed by the emitter.');
    }

    /** Not seekable: the body is produced once, by the emitter. */
    public function rewind(): void
    {
        throw new \RuntimeException('A CallbackStream is not seekable; it is streamed by the emitter.');
    }

    /** The content comes from the callback, not from writes to this stream. */
    public function isWritable(): bool
    {
        return false;
    }

    /** Not writable: the content is produced by the callback. */
    public function write(string $string): int
    {
        throw new \RuntimeException('A CallbackStream is not writable; its content is produced by its callback.');
    }

    /** There is nothing to read: the body is streamed by the emitter, not read here. */
    public function isReadable(): bool
    {
        return false;
    }

    /** Nothing to read: the body only exists when streamed by the emitter. */
    public function read(int $length): string
    {
        return '';
    }

    /** Not materializable: the body only exists when streamed by the emitter. */
    public function getContents(): string
    {
        return '';
    }

    /**
     * No metadata: this carries a callback, not a stream resource.
     *
     * @param string|null $key
     *
     * @return null
     */
    public function getMetadata(?string $key = null)
    {
        return null;
    }
}
