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

namespace Milpa\Runtime\Tests\Fixtures\Stack;

use Milpa\Runtime\Stack\ServiceSignature;
use Milpa\Runtime\Stack\SignatureProbe;

/** A signature probe that answers from a port → status map and records what it was asked. */
final class FakeSignatureProbe implements SignatureProbe
{
    /** @var list<array{int, string}> */
    public array $asked = [];

    /** @param array<int, int|null> $answers */
    public function __construct(private readonly array $answers = [])
    {
    }

    public function answer(int $port, ServiceSignature $signature): ?int
    {
        $this->asked[] = [$port, $signature->path];

        return $this->answers[$port] ?? null;
    }
}
