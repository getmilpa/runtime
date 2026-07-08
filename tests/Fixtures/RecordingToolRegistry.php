<?php

declare(strict_types=1);

namespace Milpa\Runtime\Tests\Fixtures;

use Milpa\Interfaces\Tooling\ToolRegistryInterface;
use Milpa\ValueObjects\Tooling\ToolOptions;

/**
 * A minimal {@see ToolRegistryInterface} fake that just records what was registered — enough to
 * prove the kernel's optional tool-registry seam works without depending on `milpa/tool-runtime`.
 */
final class RecordingToolRegistry implements ToolRegistryInterface
{
    /** @var list<string> */
    public array $registeredNames = [];

    /**
     * Records the tool name; the schema/callback/options are irrelevant to what this fake asserts.
     *
     * @param array<string, mixed> $inputSchema
     */
    public function register(
        string $name,
        string $description,
        array $inputSchema,
        callable $callback,
        ?ToolOptions $options = null
    ): void {
        $this->registeredNames[] = $name;
    }
}
