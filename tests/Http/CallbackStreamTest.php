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
use PHPUnit\Framework\TestCase;

/**
 * The marker body that carries a callback through a PSR-7 response (greenhouse evidence/0472): it exposes
 * the callback the emitter runs and is deliberately non-materializable, so a wrong (buffered) emission
 * yields nothing rather than a broken half-response.
 */
final class CallbackStreamTest extends TestCase
{
    public function testItCarriesTheCallbackTheEmitterWillRun(): void
    {
        $ran = false;
        $stream = new CallbackStream(function () use (&$ran): void {
            $ran = true;
        });

        ($stream->callback())();

        self::assertTrue($ran);
    }

    public function testItIsNotMaterializable(): void
    {
        $stream = new CallbackStream(static function (): void {
            echo 'never stringified';
        });

        self::assertSame('', (string) $stream);
        self::assertSame('', $stream->getContents());
        self::assertSame('', $stream->read(1024));
        self::assertTrue($stream->eof());
        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
        self::assertNull($stream->getSize());
        self::assertNull($stream->getMetadata());
        self::assertSame(0, $stream->tell());
    }

    public function testTheUnsupportedMutatorsThrow(): void
    {
        $stream = new CallbackStream(static fn (): null => null);

        $this->expectException(\RuntimeException::class);
        $stream->write('x');
    }

    public function testSeekAndRewindThrow(): void
    {
        $stream = new CallbackStream(static fn (): null => null);

        self::assertNull($stream->detach());
        $stream->close(); // no-op, no throw

        $threw = 0;
        foreach ([static fn () => $stream->seek(1), static fn () => $stream->rewind()] as $op) {
            try {
                $op();
            } catch (\RuntimeException) {
                ++$threw;
            }
        }
        self::assertSame(2, $threw);
    }
}
