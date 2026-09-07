<?php

/**
 * This file is part of Milpa Runtime — the kernel and HTTP runtime of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/runtime
 */

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Http;

use Milpa\Runtime\Http\ExceptionMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;

/**
 * A throw becomes a RESPONSE, and the message stays on this side of the wire.
 *
 * Measured before this existed (greenhouse decisions/0215 F3): a controller that threw answered 500 with
 * a zero-byte body and `text/html` whatever the caller asked for, and with `display_errors` on — which the
 * app does not control — the message and the trace went to the client.
 */
#[CoversClass(ExceptionMiddleware::class)]
final class ExceptionMiddlewareTest extends TestCase
{
    private const string SECRET = 'SUPERSECRETO-token-abc123';

    public function testAHealthyRequestPassesThroughUntouched(): void
    {
        $response = $this->middleware()->process($this->request(), $this->handler(static function (): ResponseInterface {
            return (new Psr17Factory())->createResponse(204);
        }));

        self::assertSame(204, $response->getStatusCode());
    }

    public function testAThrowBecomesARenderedFiveHundredInsteadOfAnEmptyBody(): void
    {
        $response = $this->middleware()->process($this->request(), $this->throwing());

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        self::assertNotSame('', (string) $response->getBody(), 'the measured behaviour before this was zero bytes');
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testAJsonCallerGetsJson(): void
    {
        $response = $this->middleware()->process(
            $this->request(['Accept' => 'application/json']),
            $this->throwing(),
        );

        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertFalse($body['ok']);
        self::assertSame('internal_error', $body['error']);
        self::assertNotSame('', $body['reference'] ?? '');
    }

    public function testABrowserGetsHtmlEvenWhenItAlsoAcceptsJson(): void
    {
        $browser = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

        $response = $this->middleware()->process($this->request(['Accept' => $browser]), $this->throwing());

        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
    }

    public function testTheMessageNeverReachesTheClient(): void
    {
        foreach ([[], ['Accept' => 'application/json']] as $headers) {
            foreach ([false, true] as $debug) {
                $response = $this->middleware($debug)->process($this->request($headers), $this->throwing());

                self::assertStringNotContainsString(
                    self::SECRET,
                    (string) $response->getBody(),
                    'debug=' . var_export($debug, true) . ' leaked the exception message',
                );
            }
        }
    }

    public function testDebugAddsWhereItHappenedAndNothingItSaid(): void
    {
        $response = $this->middleware(true)->process(
            $this->request(['Accept' => 'application/json']),
            $this->throwing(),
        );

        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertSame(\RuntimeException::class, $body['debug']['class'] ?? null);
        self::assertArrayHasKey('file', $body['debug']);
        self::assertArrayHasKey('line', $body['debug']);
        self::assertArrayNotHasKey('message', $body['debug'], 'the message is the one thing that must not travel');
        self::assertArrayNotHasKey('trace', $body['debug']);
    }

    public function testWithoutDebugTheBodyCarriesOnlyTheReference(): void
    {
        $response = $this->middleware()->process($this->request(['Accept' => 'application/json']), $this->throwing());
        $body = json_decode((string) $response->getBody(), true);

        self::assertIsArray($body);
        self::assertArrayNotHasKey('debug', $body);
    }

    public function testTheDetailGoesToTheLogJoinedToTheResponseByItsReference(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $lines = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->lines[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $response = $this->middleware(false, $logger)->process(
            $this->request(['Accept' => 'application/json']),
            $this->throwing(),
        );

        self::assertCount(1, $logger->lines);
        self::assertSame(self::SECRET, $logger->lines[0]['context']['message'], 'the message lives HERE, not in the response');

        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertSame(
            $logger->lines[0]['context']['reference'],
            $body['reference'],
            'the reference is what joins the two, or the log is unfindable',
        );
    }

    public function testTwoFailuresGetDifferentReferences(): void
    {
        $first = json_decode((string) $this->middleware()->process($this->request(['Accept' => 'application/json']), $this->throwing())->getBody(), true);
        $second = json_decode((string) $this->middleware()->process($this->request(['Accept' => 'application/json']), $this->throwing())->getBody(), true);

        self::assertIsArray($first);
        self::assertIsArray($second);
        self::assertNotSame($first['reference'], $second['reference']);
    }

    private function middleware(bool $debug = false, ?\Psr\Log\LoggerInterface $logger = null): ExceptionMiddleware
    {
        return new ExceptionMiddleware(new Psr17Factory(), $logger, $debug);
    }

    /** @param array<string, string> $headers */
    private function request(array $headers = []): ServerRequestInterface
    {
        return new ServerRequest('GET', '/boom', $headers);
    }

    private function throwing(): RequestHandlerInterface
    {
        return $this->handler(static function (): ResponseInterface {
            throw new \RuntimeException(self::SECRET);
        });
    }

    private function handler(\Closure $answer): RequestHandlerInterface
    {
        return new class ($answer) implements RequestHandlerInterface {
            public function __construct(private readonly \Closure $answer)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->answer)($request);
            }
        };
    }
}
