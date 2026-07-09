<?php

declare(strict_types=1);

namespace Milpa\Runtime;

/**
 * A command a plugin declares via {@see CommandProviderInterface} — the first atom of the
 * Command-as-atom wave (see `docs/library/vision-milpa-commands.md`): today it projects to a
 * `coa` subcommand, tomorrow the same definition also projects to an MCP tool, an HTTP endpoint,
 * a web SchemaForm, a TUI widget — one operation, N surfaces.
 *
 * Deliberately minimal so it can grow without breaking existing plugins: {@see $inputSchema} is
 * nullable today (no projector consumes it yet — the Command-as-atom wave's CLI-flag/MCP-tool/
 * web-SchemaForm projectors are what will), and future fields (surfaces, scopes, `mutating`) are
 * additive constructor-promoted properties, never a rename of what is here.
 */
final readonly class CommandDefinition
{
    /**
     * @param string                                     $name        The command name as invoked, e.g. `board:seed` — becomes
     *                                                                `coa board:seed` on the CLI surface.
     * @param string                                     $description One-line human-readable summary, shown by `coa inspect:commands`.
     * @param callable|array{0: class-string, 1: string} $handler     Either a plain PHP callable the
     *                                                                declaring plugin already closed over its own dependencies into,
     *                                                                or a `[class-string, method]` pair a host resolves through its DI
     *                                                                container before calling — mirrors `milpa/http`'s
     *                                                                `HandlerReference` pattern for routes. Typed `mixed` because PHP
     *                                                                does not allow the native `callable` type on a class property.
     * @param array<string, mixed>|null                  $inputSchema JSON-Schema-shaped description of the
     *                                                                command's inputs, for surfaces that derive flags/forms/tool
     *                                                                schemas from it. Null today — the discovery seam ships before any
     *                                                                projector reads this field.
     */
    public function __construct(
        public string $name,
        public string $description,
        public mixed $handler,
        public ?array $inputSchema = null,
    ) {
    }
}
