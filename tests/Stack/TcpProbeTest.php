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

use Milpa\Runtime\Stack\TcpProbe;
use PHPUnit\Framework\TestCase;

final class TcpProbeTest extends TestCase
{
    public function testDiscriminatesAListeningPortFromAClosedOne(): void
    {
        $errno = 0;
        $error = '';
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertNotFalse($server, $error);
        $name = (string) stream_socket_get_name($server, false);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);
        self::assertGreaterThan(0, $port);

        $probe = new TcpProbe();
        self::assertTrue($probe->reachable($port), 'something listens: up');

        fclose($server);
        self::assertFalse($probe->reachable($port), 'nothing listens any more: down (positive control)');
    }
}
