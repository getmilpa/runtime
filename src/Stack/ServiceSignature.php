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

namespace Milpa\Runtime\Stack;

/**
 * What a declared service answers on its own port that another program listening there would not.
 *
 * A TCP connect only says that SOMETHING accepts on the port. Measured on a developer machine: a
 * `next-server` listening on :3000 made `coa stack` report the Mercure hub `up` — the port the hub
 * would publish was taken by an unrelated app, and the reader called it the hub (greenhouse
 * evidence/1035). A signature is one HTTP request the service is REQUIRED to answer a certain way —
 * by its protocol, not by a version's wording — so a reader can tell the service from a squatter
 * (greenhouse decisions/0504). Pure data: {@see SignatureProbe} asks.
 */
final readonly class ServiceSignature
{
    /**
     * @param string    $path     the request path, starting with `/`, that the reader GETs on the probe port
     * @param list<int> $statuses the HTTP statuses the service may answer it with — more than one when the
     *                            answer depends on how the service is configured, never on its version
     */
    public function __construct(
        public string $path,
        public array $statuses,
    ) {
        if (!str_starts_with($path, '/') || preg_match('/[\s\x00-\x1f]/', $path) === 1) {
            throw new \InvalidArgumentException(\sprintf('Signature path «%s» must start with / and hold no whitespace.', $path));
        }
        if ($statuses === [] || !array_is_list($statuses)) {
            throw new \InvalidArgumentException('A signature names at least one status, as a list.');
        }
        foreach ($statuses as $status) {
            if (!\is_int($status) || $status < 100 || $status > 599) {
                throw new \InvalidArgumentException(\sprintf('Signature status %s is not an HTTP status.', var_export($status, true)));
            }
        }
    }

    /** Whether an answer is one the service gives — null (no HTTP came back) never is. */
    public function matches(?int $status): bool
    {
        return $status !== null && \in_array($status, $this->statuses, true);
    }
}
