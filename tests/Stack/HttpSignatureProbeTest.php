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

use Milpa\Runtime\Stack\HttpSignatureProbe;
use Milpa\Runtime\Stack\ServiceSignature;
use PHPUnit\Framework\TestCase;

/**
 * Real programs on real ports: a «hub» that answers its path the way the protocol obliges, a squatter
 * that answers something else, a stream that never ends, and a program that does not speak HTTP.
 */
final class HttpSignatureProbeTest extends TestCase
{
    /** @var list<resource> */
    private array $processes = [];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/milpa-signature-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            proc_terminate($process, 9);
            proc_close($process);
        }
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testTellsTheServiceFromASquatterByTheStatusItAnswers(): void
    {
        $signature = new ServiceSignature('/.well-known/mercure', [400]);
        $hub = $this->serve('hub', '<?php http_response_code(str_starts_with($_SERVER["REQUEST_URI"], "/.well-known/mercure") ? 400 : 404); echo "Missing topic";');
        $squatter = $this->serve('squatter', '<?php http_response_code(404); echo "<h1>next</h1>";');
        $probe = new HttpSignatureProbe();

        self::assertSame(400, $probe->answer($hub, $signature), 'the hub answers its path as its protocol obliges');
        self::assertSame(404, $probe->answer($squatter, $signature), 'the squatter accepts the port and answers otherwise');
    }

    public function testReadsTheStatusLineOfAStreamWithoutWaitingForItsEnd(): void
    {
        $stream = $this->serve('stream', '<?php header("Content-Type: text/event-stream"); echo ": open\n\n"; flush(); sleep(10);');
        $started = microtime(true);
        self::assertSame(200, (new HttpSignatureProbe())->answer($stream, new ServiceSignature('/', [200])));
        self::assertLessThan(3.0, microtime(true) - $started, 'the reader was held by a stream that never ends');
    }

    public function testWhatDoesNotAnswerHttpIsNullNeverAnException(): void
    {
        $signature = new ServiceSignature('/', [200]);
        // A listener that accepts (the kernel completes the handshake) and never says a word.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertNotFalse($server, (string) $error);
        $port = self::portOf($server);
        $probe = new HttpSignatureProbe();
        // Nothing accepts the connection into a reply within the timeout: silence is null.
        $started = microtime(true);
        self::assertNull($probe->answer($port, $signature));
        self::assertLessThan(2.0, microtime(true) - $started, 'silence must cost the timeout, not more');
        fclose($server);
        // POSITIVE CONTROL: the same port, now closed — refused is null too.
        self::assertNull($probe->answer($port, $signature));
    }

    /** Starts `php -S` over a one-file docroot and returns its port once it answers. */
    private function serve(string $name, string $code): int
    {
        mkdir($this->dir . '/' . $name);
        file_put_contents($this->dir . '/' . $name . '/router.php', $code);
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertNotFalse($server, (string) $error);
        $port = self::portOf($server);
        fclose($server);
        $process = proc_open([\PHP_BINARY, '-S', '127.0.0.1:' . $port, $this->dir . '/' . $name . '/router.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        self::assertIsResource($process);
        $this->processes[] = $process;
        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return $port;
            }
            usleep(50_000);
        }
        self::fail('php -S never listened on ' . $port);
    }

    /** @param resource $server */
    private static function portOf($server): int
    {
        $name = (string) stream_socket_get_name($server, false);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
