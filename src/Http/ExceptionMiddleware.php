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

namespace Milpa\Runtime\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Turns an unhandled throwable into a RESPONSE, in the shape the caller asked for.
 *
 * Measured on a fresh app before this existed (greenhouse decisions/0215, F3): a controller that throws
 * answered `500` with a **zero-byte body** and `Content-Type: text/html` whatever the request's `Accept`
 * said — and with `display_errors` on, which the app does not control, the exception message, the file
 * path and the whole stack trace went to the CLIENT. A JSON client got HTML; a browser got nothing.
 *
 * ── THE MESSAGE NEVER CROSSES, NOT EVEN IN DEBUG ────────────────────────────────────────────────────
 *
 * `decisions/0215` asks for the detail under a declared `debug`, and for an authority exception's message
 * never to leak. The obvious build is a marker interface the authority exceptions implement — and it was
 * rejected after measuring it: `milpa/auth`, which owns five of the six authority exceptions in the
 * family, does not depend on `milpa/core`, so the marker could not live where the framework's exception
 * contracts live. Worse than the edge is the shape: a marker somebody must REMEMBER to add is a default
 * that fails open, and an authority exception written next year would leak until someone noticed.
 *
 * So the rule is structural instead of enumerated: **no exception message and no stack trace ever reaches
 * the client.** Debug adds the class, the file and the line — enough to find it — and the message and the
 * trace go to the log, joined to the response by a reference the body carries. A rule that cannot be
 * forgotten beats a list that can.
 *
 * ── WHAT IT DELIBERATELY DOES NOT DO ────────────────────────────────────────────────────────────────
 *
 * It does not map exception types to status codes. Everything unhandled is a 500, because deciding that
 * some exception means 403 needs a contract that says which — the same enumeration problem — and a wrong
 * guess turns a bug into a silent «you are not allowed». A 403 is something a policy DECIDES, not
 * something a failure is guessed to be.
 */
final class ExceptionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $debug = false,
    ) {
    }

    /**
     * Runs the rest of the pipeline, and answers a rendered 500 if anything escapes it.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $failure) {
            // The reference is what joins this response to the log line that holds the real detail. It is
            // random per failure, so it identifies an OCCURRENCE and never the app, the route or the user.
            $reference = bin2hex(random_bytes(8));

            $this->logger?->error('Unhandled {class} at {file}:{line} — {message} [ref {reference}]', [
                'class' => $failure::class,
                'file' => $failure->getFile(),
                'line' => $failure->getLine(),
                'message' => $failure->getMessage(),
                'reference' => $reference,
                'exception' => $failure,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
            ]);

            return $this->render($request, $failure, $reference);
        }
    }

    /** The response itself, JSON when the caller asked for it and HTML otherwise. */
    private function render(ServerRequestInterface $request, \Throwable $failure, string $reference): ResponseInterface
    {
        $wantsJson = $this->wantsJson($request);
        $response = $this->responses->createResponse(500)
            ->withHeader('Content-Type', $wantsJson ? 'application/json; charset=utf-8' : 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');

        $response->getBody()->write($wantsJson ? $this->json($failure, $reference) : $this->html($failure, $reference));

        return $response;
    }

    /**
     * Whether the caller asked for JSON.
     *
     * A browser sends `text/html` first and a programme sends `application/json`; when neither is named
     * the answer is HTML, because a human reading a blank page learns nothing and a programme that did not
     * say what it wanted can read a page.
     */
    private function wantsJson(ServerRequestInterface $request): bool
    {
        $accept = strtolower($request->getHeaderLine('Accept'));
        if (str_contains($accept, 'application/json') || str_contains($accept, '+json')) {
            return !str_contains($accept, 'text/html')
                || strpos($accept, 'json') < (int) strpos($accept, 'text/html');
        }

        return false;
    }

    /** @return string the JSON body: the reference always, the location only under debug */
    private function json(\Throwable $failure, string $reference): string
    {
        $body = ['ok' => false, 'error' => 'internal_error', 'reference' => $reference];
        if ($this->debug) {
            $body['debug'] = $this->location($failure);
        }

        return (string) json_encode($body, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /** @return string the HTML body — plain, self-contained, and carrying no message */
    private function html(\Throwable $failure, string $reference): string
    {
        $detail = '';
        if ($this->debug) {
            $where = $this->location($failure);
            $detail = sprintf(
                '<p class="where"><code>%s</code><br>%s:%d</p>',
                htmlspecialchars($where['class'], \ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($where['file'], \ENT_QUOTES, 'UTF-8'),
                $where['line'],
            );
        }

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Something failed</title><style>'
            . 'body{font:16px/1.6 system-ui,sans-serif;margin:0;display:grid;place-items:center;min-height:100vh;'
            . 'background:#faf9f7;color:#1b1a17}main{max-width:34rem;padding:2rem}h1{font-size:1.3rem;margin:0 0 .5rem}'
            . 'p{margin:0 0 .75rem}code{font:14px ui-monospace,monospace;background:#efece7;padding:.1rem .35rem;border-radius:.2rem}'
            . '.ref{color:#6b675f;font-size:.9rem}.where{font-size:.9rem;color:#6b675f}'
            . '</style></head><body><main>'
            . '<h1>Something failed while answering this request.</h1>'
            . '<p>The detail is in this app&rsquo;s log, not on this page.</p>'
            . $detail
            . '<p class="ref">Reference <code>' . htmlspecialchars($reference, \ENT_QUOTES, 'UTF-8') . '</code></p>'
            . '</main></body></html>';
    }

    /**
     * Where it happened — never WHAT it said.
     *
     * @return array{class: string, file: string, line: int}
     */
    private function location(\Throwable $failure): array
    {
        return ['class' => $failure::class, 'file' => $failure->getFile(), 'line' => $failure->getLine()];
    }
}
