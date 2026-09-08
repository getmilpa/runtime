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

namespace Milpa\Runtime\Event;

use Milpa\Events\CapabilityResolvedEvent;
use Milpa\Events\InterceptionSlot;
use Milpa\Events\KernelBootedEvent;
use Milpa\Events\PluginBootedEvent;
use Milpa\Events\PluginBootingEvent;
use Milpa\Interfaces\Event\DeclaredEvents;
use Milpa\Interfaces\Event\DeclaresEvents;
use Milpa\Interfaces\Event\EventDeclaration;
use Milpa\Resolver\Events\ArchitectureResolvedEvent;
use Milpa\Runtime\Boot\InlinePluginBootStrategy;
use Milpa\Runtime\Kernel;

/**
 * Every event this package dispatches, declared once, from the same names its `dispatch()` sites use.
 *
 * The constants ARE the strings handed to `dispatch()`: {@see Kernel::boot()} and
 * {@see InlinePluginBootStrategy} read them from here, so a declaration and its dispatch cannot drift
 * apart through a retyped string. The moment {@see Kernel::boot()} holds a dispatcher — before any
 * dispatch — it hands it {@see declarations()} if that dispatcher implements {@see DeclaredEvents}; any
 * other dispatcher is told nothing and every dispatch keeps working, because the authority on «what
 * events exist» is the emitter and the one place every dispatch passes through is the dispatcher
 * (greenhouse decisions/0228).
 *
 * A declaration made at construction only reaches a process that constructs the emitter. Implementing
 * {@see DeclaresEvents} and naming this class under `extra.milpa.events` in the package manifest lets a
 * host read the same list from what Composer really installed and declare it on behalf of an emitter
 * this process will never build.
 */
final class RuntimeEvents implements DeclaresEvents
{
    /** The resolver accepted the whole plugin graph; carries its full report. Fires before any plugin boots. */
    public const ARCHITECTURE_RESOLVED = 'architecture.resolved';

    /** The dependency-ordered load list is known; dispatched right after {@see ARCHITECTURE_RESOLVED}, before any plugin boots. */
    public const CAPABILITY_RESOLVED = 'capability.resolved';

    /** A plugin is about to boot; a listener may veto it through the {@see InterceptionSlot} under `slot`. */
    public const PLUGIN_BOOTING = 'plugin.booting';

    /** A plugin's `boot()` just ran. Never fires for a vetoed plugin. */
    public const PLUGIN_BOOTED = 'plugin.booted';

    /** Every plugin booted and the route table is assembled; the kernel is about to be returned. */
    public const KERNEL_BOOTED = 'kernel.booted';

    /** The payload key every event of this package carries its readonly subject under (family convention). */
    public const SUBJECT_KEY = 'event';

    /** The payload key the interceptable event carries its {@see InterceptionSlot} under (core's keystone contract). */
    public const SLOT_KEY = 'slot';

    /**
     * One declaration per event name this package dispatches, in the order a boot emits them.
     *
     * `dispatchedBy` names the class whose code calls `dispatch()`; `subjectKey`/`subjectType` are
     * the key and the value class the payload really carries; only {@see PLUGIN_BOOTING} is
     * interceptable, because only its payload carries an {@see InterceptionSlot}. Under
     * {@see \Milpa\Runtime\Boot\PluginsManagerBootStrategy} the plugin phase and `kernel.booted`
     * are the manager's dispatches, declared by its own package; these declarations describe what
     * THIS package dispatches on the default, inline path.
     *
     * @return list<EventDeclaration>
     */
    public static function declarations(): array
    {
        return [
            new EventDeclaration(
                name: self::ARCHITECTURE_RESOLVED,
                dispatchedBy: InlinePluginBootStrategy::class,
                when: 'The resolver accepted the whole plugin graph, before any plugin boots.',
                subjectKey: self::SUBJECT_KEY,
                subjectType: ArchitectureResolvedEvent::class,
            ),
            new EventDeclaration(
                name: self::CAPABILITY_RESOLVED,
                dispatchedBy: InlinePluginBootStrategy::class,
                when: 'The dependency-ordered load list is known, right after architecture.resolved and before any plugin boots.',
                subjectKey: self::SUBJECT_KEY,
                subjectType: CapabilityResolvedEvent::class,
            ),
            new EventDeclaration(
                name: self::PLUGIN_BOOTING,
                dispatchedBy: InlinePluginBootStrategy::class,
                when: 'A plugin is about to boot; stopping the slot vetoes it.',
                subjectKey: self::SUBJECT_KEY,
                subjectType: PluginBootingEvent::class,
                interceptable: true,
            ),
            new EventDeclaration(
                name: self::PLUGIN_BOOTED,
                dispatchedBy: InlinePluginBootStrategy::class,
                when: 'A plugin\'s boot() just ran; never fires for a vetoed plugin.',
                subjectKey: self::SUBJECT_KEY,
                subjectType: PluginBootedEvent::class,
            ),
            new EventDeclaration(
                name: self::KERNEL_BOOTED,
                dispatchedBy: Kernel::class,
                when: 'Every plugin booted and the route table is assembled, just before the kernel is returned.',
                subjectKey: self::SUBJECT_KEY,
                subjectType: KernelBootedEvent::class,
            ),
        ];
    }
}
