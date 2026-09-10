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

namespace Milpa\Runtime\Tests\Observability;

use Milpa\Container\DIContainer;
use Milpa\Runtime\Http\ExceptionMiddleware;
use Milpa\Runtime\Kernel;
use Milpa\Runtime\Observability\ErrorLogLogger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * THE 500 PAGE NAMES A LOG — these are the two reasons it named nothing.
 *
 * Measured on a fresh app (greenhouse decisions/0286): a route that threw answered a rendered 500 whose
 * body reads «the detail is in this app's log» with a reference, and that reference appeared in ZERO
 * files under the app. Two separate gaps, and fixing either alone leaves the promise empty:
 *
 *  1. the kernel ACCEPTED a `logger` and kept it as a local, so no host could ask for the one it passed;
 *  2. nothing in the family wrote to the app's log, so there was no destination to pass.
 *
 * The half that protects the client was already right and is not re-tested here — {@see ExceptionMiddlewareTest}
 * owns it. What this file asserts is that the OTHER half now has somewhere to go.
 */
#[CoversClass(ErrorLogLogger::class)]
#[CoversClass(Kernel::class)]
final class TheLogThe500PageNamesExistsTest extends TestCase
{
    /** @var string|false */
    private $previousDestination = false;

    protected function setUp(): void
    {
        $this->previousDestination = ini_get('error_log');
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousDestination === false ? '' : $this->previousDestination);
    }

    /** The logger the host passes is reachable through the container, under the PSR interface. */
    public function testTheKernelHandsBackTheLoggerItWasGiven(): void
    {
        $mine = new ErrorLogLogger();
        $container = new DIContainer();

        Kernel::boot(['root' => \dirname(__DIR__, 2), 'plugins' => [], 'container' => $container, 'logger' => $mine]);

        self::assertSame($mine, $container->get(LoggerInterface::class), 'the host passed a logger; asking for the contract must return THAT one');
    }

    /** Absent a logger the container still answers — silent by request, not by omission. */
    public function testAHostThatPassesNoneStillGetsALoggerToAskFor(): void
    {
        $container = new DIContainer();

        Kernel::boot(['root' => \dirname(__DIR__, 2), 'plugins' => [], 'container' => $container]);

        self::assertInstanceOf(NullLogger::class, $container->get(LoggerInterface::class), 'nothing was passed, so the default is the one that says nothing on purpose');
    }

    /** The reference the 500 body shows is findable in the app's log — the whole point. */
    public function testTheReferenceOnThePageIsGrepableInTheLog(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'milpa-log-');
        self::assertIsString($log);
        ini_set('error_log', $log);

        $factory = new Psr17Factory();
        $response = (new ExceptionMiddleware($factory, new ErrorLogLogger(), false))->process(
            new ServerRequest('GET', '/lab/throw', ['Accept' => 'application/json']),
            new class () implements RequestHandlerInterface {
                public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
                {
                    throw new \RuntimeException('SECRET-DETAIL-THAT-MUST-NOT-REACH-THE-CLIENT');
                }
            },
        );

        $body = (string) $response->getBody();
        $reference = json_decode($body, true)['reference'] ?? '';
        self::assertIsString($reference);
        self::assertNotSame('', $reference, 'the body promises a reference');

        $written = (string) file_get_contents($log);
        unlink($log);

        self::assertStringContainsString($reference, $written, 'the reference the client was given must appear in the log, or the page named a log that holds nothing');
        self::assertStringContainsString('SECRET-DETAIL-THAT-MUST-NOT-REACH-THE-CLIENT', $written, 'and the detail the client never sees must be the thing the log kept');
        self::assertStringNotContainsString('SECRET-DETAIL-THAT-MUST-NOT-REACH-THE-CLIENT', $body, 'positive control on the other half: the message still does not cross the wire');
    }

    /**
     * THE FLOOR: an app's narration does not reach the app's ERROR log, and a failure does.
     *
     * Measured the moment this logger was first handed to `Kernel::boot()`: an empty boot — zero plugins,
     * zero requests — wrote SIX lines, all `debug`, because the dispatcher narrates every dispatch. That
     * is not a cosmetic problem: it buries the one `error` line the 500 page's reference points at.
     */
    public function testTheNarrationOfAWorkingAppStaysOutOfTheErrorLog(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'milpa-log-');
        self::assertIsString($log);
        ini_set('error_log', $log);

        $default = new ErrorLogLogger();
        $default->debug('narrating a dispatch');
        $default->info('narrating a boot');
        $default->warning('something is off');
        $default->error('something failed');

        $written = (string) file_get_contents($log);

        self::assertStringNotContainsString('narrating a dispatch', $written, 'debug is below the default floor');
        self::assertStringNotContainsString('narrating a boot', $written, 'and so is info');
        self::assertStringContainsString('something is off', $written, 'warning is the floor, so it passes');
        self::assertStringContainsString('something failed', $written, 'and error — which is what the 500 path writes — must always pass');

        // Positive control: the floor is a CHOICE, not a hard-coded silence. Asked for debug, it narrates.
        file_put_contents($log, '');
        (new ErrorLogLogger(LogLevel::DEBUG))->debug('narrating a dispatch');
        self::assertStringContainsString('narrating a dispatch', (string) file_get_contents($log), 'a host that asks for the narration gets it');

        unlink($log);
    }

    /** A `{placeholder}` is filled from the context, and the exception's trace is not rendered into the line. */
    public function testALineIsInterpolatedAndCarriesNoTrace(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'milpa-log-');
        self::assertIsString($log);
        ini_set('error_log', $log);

        (new ErrorLogLogger())->error('Unhandled {class} at {file}:{line}', [
            'class' => 'RuntimeException',
            'file' => '/app/src/Thrower.php',
            'line' => 21,
            'exception' => new \RuntimeException('TRACE-BEARING'),
        ]);

        $written = (string) file_get_contents($log);
        unlink($log);

        self::assertStringContainsString('[error] Unhandled RuntimeException at /app/src/Thrower.php:21', $written);
        self::assertStringNotContainsString('TRACE-BEARING', $written, 'the exception object is not rendered into the line — its trace belongs to the host handler');
        self::assertStringNotContainsString('{class}', $written, 'an unfilled placeholder means the interpolation never ran');
    }
}
