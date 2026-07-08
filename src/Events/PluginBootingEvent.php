<?php

declare(strict_types=1);

namespace Milpa\Runtime\Events;

/**
 * Dispatched immediately BEFORE a plugin's boot() runs ('plugin.booting').
 *
 * Readonly per the event-driven family convention (core/events KEYSTONE) — this event
 * carries no mutable state of its own. Veto lives exclusively in the
 * {@see \Milpa\Events\InterceptionSlot} dispatched ALONGSIDE this event
 * (payload shaped `['event' => $this, 'slot' => $slot]`), never on the event itself.
 *
 * A listener that calls `$slot->stop()` — e.g. a feature-flag or environment plugin
 * vetoing another plugin's activation — skips this plugin's boot() entirely: boot()
 * is never called, 'plugin.booted' is never emitted for it, and its routes/tools are
 * not registered either. The overall plugin boot loop ({@see \Milpa\Runtime\Kernel::boot()})
 * continues with the next plugin regardless.
 */
final class PluginBootingEvent
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
