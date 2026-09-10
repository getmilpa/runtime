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

namespace Milpa\Runtime\Observability;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * A PSR-3 logger that writes to THE APP'S LOG — the one the host already configured.
 *
 * It exists because {@see \Milpa\Runtime\Http\ExceptionMiddleware} makes a promise the framework was
 * not keeping. Measured on a fresh app (greenhouse decisions/0286): a route that throws answered a
 * rendered 500 whose body reads «the detail is in this app's log» with a reference — and the app was
 * built with `new ExceptionMiddleware($psr17, null, …)`, so the reference joined the response to
 * NOTHING. The half that protects the client worked (no message, no trace, measured); the half that
 * keeps the detail findable did not exist. A page that names a log there is no log for is worse than
 * one that says nothing: it sends whoever reads it looking for a file.
 *
 * ── WHY `error_log()` AND NOT A FILE THIS PACKAGE PICKS ─────────────────────────────────────────────
 *
 * Every deployment ALREADY has an app log and the host already chose where it is: php-fpm has its
 * `error_log`, Apache and nginx theirs, `php -S` prints to the console the developer is watching, and
 * a container ships it to stdout. `error_log()` is the one destination that is correct in all of them
 * without this package guessing a path, creating a directory, or owning rotation — and a path guessed
 * wrong fails the same way `null` did, silently, while looking configured.
 *
 * An app that wants structured logs elsewhere passes its OWN PSR-3 logger to `Kernel::boot(['logger' =>
 * …])`; this is the default that makes the default honest, not a logging policy.
 *
 * The level rides in the line because `error_log()` has no level of its own, and the context is appended
 * as JSON so a line stays greppable by the reference the 500 page showed — which is the whole point.
 *
 * ── WHY THERE IS A FLOOR, AND WHY IT IS `warning` ──────────────────────────────────────────────────
 *
 * A host will pass this to `Kernel::boot(['logger' => …])`, because that is the one logger the whole
 * app then shares. Measured the moment it was: an EMPTY boot — zero plugins, zero requests — wrote
 * SIX lines, all `debug`, because the event dispatcher narrates every dispatch to whatever logger it
 * holds. Unfiltered, this class turns the app's error log into a firehose and the `error` line the 500
 * page points at becomes the hardest thing in the file to find.
 *
 * So the default floor is `warning`: everything that means «something is wrong» passes, and the
 * narration of a working app does not. A host that wants the narration asks for it — `new
 * ErrorLogLogger(LogLevel::DEBUG)` — which is the shape where the noisy choice is the explicit one.
 */
final class ErrorLogLogger extends AbstractLogger
{
    /**
     * PSR-3's eight levels, most severe first — the order the floor is compared in.
     *
     * A level this list does not know (PSR-3 allows any value in `log()`) is treated as PASSING rather
     * than dropped: a caller who invented a level is saying something the app's own vocabulary does not
     * cover, and swallowing it because it was unrecognised is how the `null` this class replaced behaved.
     */
    private const array SEVERITY = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    /** Records at or above this severity are written; the rest are dropped. */
    public function __construct(private readonly string $floor = LogLevel::WARNING)
    {
    }

    /**
     * One line per record: `[level] message {context}`, with `{placeholders}` interpolated.
     *
     * PSR-3 asks that a `{key}` in the message be replaced by `$context['key']` when that value is a
     * string or stringable, and that unmatched braces be left alone. The exception itself — which PSR-3
     * puts under `exception` — is deliberately NOT rendered: its trace is the thing this whole path
     * exists to keep off the wire, and a trace in the log is fine but belongs to the host's own
     * handler, not to a line this package composes.
     *
     * @param mixed               $level
     * @param array<mixed, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!$this->passes($level)) {
            return;
        }

        $line = self::interpolate((string) $message, $context);
        $rest = $context;
        unset($rest['exception']);
        $tail = $rest === [] ? '' : ' ' . (json_encode($rest, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '');

        error_log('[' . (\is_scalar($level) ? (string) $level : 'log') . '] ' . $line . $tail);
    }

    /**
     * Whether a record at `$level` clears the floor this logger was built with.
     *
     * @param mixed $level
     */
    private function passes($level): bool
    {
        if (!\is_string($level) || !isset(self::SEVERITY[$level])) {
            return true;
        }

        return self::SEVERITY[$level] <= (self::SEVERITY[$this->floor] ?? self::SEVERITY[LogLevel::WARNING]);
    }

    /**
     * `{key}` becomes `$context['key']` when that value can be written as a string.
     *
     * @param array<mixed, mixed> $context
     */
    private static function interpolate(string $message, array $context): string
    {
        $pairs = [];
        foreach ($context as $key => $value) {
            if (!\is_string($key)) {
                continue;
            }
            if (\is_string($value) || $value instanceof \Stringable || \is_int($value) || \is_float($value) || \is_bool($value) || $value === null) {
                $pairs['{' . $key . '}'] = \is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? 'null');
            }
        }

        return $pairs === [] ? $message : strtr($message, $pairs);
    }
}
