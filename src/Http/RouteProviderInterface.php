<?php

declare(strict_types=1);

namespace Milpa\Runtime\Http;

use Milpa\Http\Routing\Route;

/**
 * Declares that a plugin contributes routes to the kernel's route table.
 *
 * This is the routing counterpart of core's `ToolProviderInterface`/`EventSubscriberInterface`
 * auto-registration seam: {@see \Milpa\Runtime\Kernel::boot()} checks every successfully booted
 * plugin for this interface and merges its {@see routes()} into the {@see \Milpa\Http\Routing\Router} it builds —
 * the same "instanceof, then auto-wire" pattern the family already uses, applied to `milpa/http`'s
 * `Route` value object instead of inventing a second routing abstraction.
 */
interface RouteProviderInterface
{
    /**
     * Routes this plugin contributes to the kernel's route table, already bound to a handler
     * (`Route::isBound()` MUST be true for every entry — the kernel does no attribute scanning).
     *
     * @return list<Route>
     */
    public function routes(): array;
}
