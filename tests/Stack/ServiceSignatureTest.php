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

namespace Milpa\Runtime\Tests\Stack;

use Milpa\Runtime\Stack\ServiceDeclaration;
use Milpa\Runtime\Stack\ServiceSignature;
use PHPUnit\Framework\TestCase;

final class ServiceSignatureTest extends TestCase
{
    public function testAPathAndAStatusAreData(): void
    {
        // A Mercure hub: 400 when it lets anonymous subscribers in, 401 when it does not (greenhouse evidence/1037).
        $signature = new ServiceSignature('/.well-known/mercure', [400, 401]);
        self::assertSame('/.well-known/mercure', $signature->path);
        self::assertSame([400, 401], $signature->statuses);
        self::assertTrue($signature->matches(400));
        self::assertTrue($signature->matches(401));
        self::assertFalse($signature->matches(404), 'the next-server that took :3000');
        self::assertFalse($signature->matches(null), 'no HTTP is never the service');
        $service = new ServiceDeclaration(name: 'hub', image: 'h', signature: $signature);
        self::assertSame($signature, $service->signature);
        self::assertNull((new ServiceDeclaration(name: 'hub', image: 'h'))->signature, 'optional: a declaration without one still reads by TCP');
    }

    public function testRefusesWhatIsNotARequestPathOrAStatus(): void
    {
        foreach ([['well-known', 200], ["/a\r\nHost: x", 200], ['/a b', 200], ['/ok', 99], ['/ok', 600]] as [$path, $status]) {
            try {
                new ServiceSignature($path, [$status]);
                self::fail(var_export([$path, $status], true) . ' must be refused');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
        foreach ([[], [1 => 400], ['400']] as $statuses) {
            try {
                new ServiceSignature('/ok', $statuses);
                self::fail(var_export($statuses, true) . ' must be refused');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
