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

namespace Milpa\Runtime\Tests\Http;

use Milpa\Runtime\Http\CallbackStream;
use Milpa\Runtime\Http\ResponseEmitter;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * The emitter that lets a handler stream (greenhouse evidence/0472): a normal response is emitted the same
 * buffered way the front controller always did, while a {@see CallbackStream} body is STREAMED by running
 * its callback instead of stringifying it. The status and header sinks are spied so emission is observable
 * without a live SAPI.
 */
final class ResponseEmitterTest extends TestCase
{
    public function testAnOrdinaryResponseIsEmittedStatusHeadersAndBufferedBody(): void
    {
        $status = null;
        $headers = [];
        $emitter = new ResponseEmitter(
            function (int $code) use (&$status): void {
                $status = $code;
            },
            function (string $line) use (&$headers): void {
                $headers[] = $line;
            },
        );

        $response = new Response(201, ['Content-Type' => 'application/json', 'X-A' => 'b'], '{"ok":true}');

        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        self::assertSame(201, $status);
        self::assertContains('Content-Type: application/json', $headers);
        self::assertContains('X-A: b', $headers);
        self::assertSame('{"ok":true}', $output);
    }

    public function testACallbackStreamBodyIsStreamedByRunningItsCallback(): void
    {
        $status = null;
        $headers = [];
        $flushed = false;
        $emitter = new ResponseEmitter(
            function (int $code) use (&$status): void {
                $status = $code;
            },
            function (string $line) use (&$headers): void {
                $headers[] = $line;
            },
            // No-op flusher: the real one would end this test's capture buffer before we could read it.
            function () use (&$flushed): void {
                $flushed = true;
            },
        );

        // A CallbackStream body writes in pieces — the shape SSE needs — rather than being stringified.
        $body = new CallbackStream(static function (): void {
            echo "event: tick\n";
            echo "data: 1\n\n";
        });
        $response = new Response(200, ['Content-Type' => 'text/event-stream'], $body);

        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        self::assertSame(200, $status);
        self::assertContains('Content-Type: text/event-stream', $headers);
        self::assertSame("event: tick\ndata: 1\n\n", $output);
        self::assertTrue($flushed, 'output buffering is defeated before streaming');
    }

    public function testABufferedBodyDoesNotDefeatOutputBuffering(): void
    {
        $flushed = false;
        $emitter = new ResponseEmitter(
            static fn (int $code): int => $code,
            static fn (string $line): null => null,
            function () use (&$flushed): void {
                $flushed = true;
            },
        );

        ob_start();
        $emitter->emit(new Response(200, [], 'plain'));
        ob_end_clean();

        self::assertFalse($flushed, 'a normal response must not touch output buffers');
    }

    public function testItUsesPhpsOwnStatusSinkByDefault(): void
    {
        // Exercises the production default status sink (http_response_code, a no-op under CLI) without a
        // live SAPI. The header and buffer-flush defaults are SAPI-bound and are proved on cattle over a
        // real socket, not here.
        $emitter = new ResponseEmitter(null, static fn (string $line): null => null, static fn (): null => null);

        ob_start();
        $emitter->emit(new Response(204, [], ''));
        $output = (string) ob_get_clean();

        self::assertSame('', $output);
    }

    public function testMultipleValuesOfOneHeaderAreEachEmitted(): void
    {
        $headers = [];
        $emitter = new ResponseEmitter(
            static fn (int $code): int => $code,
            function (string $line) use (&$headers): void {
                $headers[] = $line;
            },
        );

        $response = (new Response(200))
            ->withAddedHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'b=2');

        ob_start();
        $emitter->emit($response);
        ob_end_clean();

        self::assertContains('Set-Cookie: a=1', $headers);
        self::assertContains('Set-Cookie: b=2', $headers);
    }
}
