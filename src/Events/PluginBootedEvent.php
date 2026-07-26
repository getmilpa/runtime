<?php

/**
 * This file is part of Milpa Runtime — the bootable kernel of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/runtime
 */

declare(strict_types=1);

namespace Milpa\Runtime\Events;

/**
 * Dispatched immediately AFTER a plugin's boot() returns successfully ('plugin.booted').
 *
 * Readonly, POST, no slot — pure notification for audit/observability plugins.
 * NOT emitted when a 'plugin.booting' listener vetoed this plugin's boot (see
 * {@see PluginBootingEvent}).
 */
final class PluginBootedEvent
{
    /**
     * @param string               $pluginName Plugin name, as declared in its `#[PluginMetadata]`.
     * @param array<string, mixed> $metadata   Full plugin metadata resolved for this boot (name,
     *                                         version, author, site, type, provides/requires/suggests).
     */
    public function __construct(
        public readonly string $pluginName,
        public readonly array $metadata = [],
    ) {
    }
}
