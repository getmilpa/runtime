<p align="center">
  <a href="https://github.com/getmilpa">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-dark.svg">
      <img src="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-light.svg" alt="Milpa" width="300">
    </picture>
  </a>
</p>

# Milpa Runtime

> The **bootable Milpa kernel** — composes `milpa/core`, `milpa/container`, `milpa/events`, `milpa/http` and `milpa/resolver` into a running app with a config-driven plugin registry, architecture resolution before boot, and lifecycle events. Zero database, zero magic.

[![CI](https://github.com/getmilpa/runtime/actions/workflows/ci.yml/badge.svg)](https://github.com/getmilpa/runtime/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/milpa/runtime.svg)](https://packagist.org/packages/milpa/runtime)
[![PHP](https://img.shields.io/badge/php-%E2%89%A5%208.3-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-API%20reference-blue.svg)](https://getmilpa.github.io/runtime/)

`milpa/runtime` is where the rest of the family stops being separate packages and becomes an
app. `Kernel::boot()` wires a DI container, an event dispatcher, a pre-boot architecture
resolution over every configured plugin, an ordered boot loop that emits lifecycle events at each step,
and a route table assembled from whatever plugins contribute one. The active-plugins list is
whatever `list<class-string>` the caller passes in — a config array, a file `require`d into
that array, or filesystem discovery the caller performs beforehand. **No Doctrine, no legacy
`Milpa\Web`, no database-backed plugin registry** — those, if you want them, live in your host
application or a plugin you add on top.

## Install

```bash
composer require milpa/runtime
```

## Quick example

A plugin declares itself with `#[PluginMetadata]` and, optionally, contributes routes by
implementing `RouteProviderInterface`:

```php
use Milpa\Attributes\PluginMetadata;
use Milpa\Http\HttpMethod;
use Milpa\Http\Routing\HandlerReference;
use Milpa\Http\Routing\Route;
use Milpa\Http\Routing\RouteResult;
use Milpa\Interfaces\Di\DIContainerInterface;
use Milpa\Interfaces\Plugin\PluginInterface;
use Milpa\Runtime\Http\RouteProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HelloController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $name = $request->getAttribute(RouteResult::ATTRIBUTE)?->parameter('name', 'world') ?? 'world';

        return new \Nyholm\Psr7\Response(200, ['Content-Type' => 'text/plain'], "hello, {$name}");
    }
}

#[PluginMetadata(version: '1.0.0', author: 'Acme', site: 'https://example.test', name: 'HelloPlugin', type: 'Web')]
final class HelloPlugin implements PluginInterface, RouteProviderInterface
{
    public function __construct(private readonly DIContainerInterface $container)
    {
    }

    public function boot(): void
    {
    }

    public function install(): void
    {
    }

    public function uninstall(): void
    {
    }

    public function enable(): void
    {
    }

    public function disable(): void
    {
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return [
            new Route(
                path: '/hello/{name}',
                methods: HttpMethod::GET,
                name: 'hello',
                handler: new HandlerReference(HelloController::class, 'handle'),
            ),
        ];
    }
}
```

`Kernel::boot()` builds the container, resolves the architecture, boots the configured plugins in
the order the resolution's own report dictates — its `loadOrder[]`, still `provides` → `requires`,
ties keeping the config order — and assembles the route table; `RequestHandler` matches a real
PSR-7 request against it and dispatches to the resolved controller:

```php
use Milpa\Runtime\Http\RequestHandler;
use Milpa\Runtime\Kernel;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;

$kernel = Kernel::boot(['plugins' => [HelloPlugin::class]]);
$kernel->bootedPluginNames(); // -> ['HelloPlugin']

$handler = new RequestHandler($kernel, new Psr17Factory());
$response = $handler->handle(new ServerRequest('GET', '/hello/milpa'));

$response->getStatusCode();    // -> 200
(string) $response->getBody(); // -> 'hello, milpa'
```

No plugin can leave the boot loop undetected: `boot()` resolves the whole architecture graph
through `milpa/resolver` (each plugin's `#[PluginMetadata]` ingested by `AttributeLoader`, the
graph resolved by `GraphResolver`) and throws `ArchitectureBlockedException` — a
`PluginDependencyException` subclass, so every existing catch keeps working — *before* any plugin
boots when the graph is blocked. The exception carries the full `ResolutionReport` on `->report`,
and its message is the report's own learnable first line: the error code, why it failed, the
first fix, and an Academy learn link. The same resolution also *orders* the boot: the report's
`loadOrder[]` (a dependency cycle blocks pre-boot as a learnable `MILPA_DEPENDENCY_CYCLE`, never
a bare "circular dependency" crash). Pass `hostProfile` (a `HostProfile::fromArray()` shape) in
the config to resolve against your own architectural profile — absent, a deliberately permissive
default keeps every graph that booted before booting still — and `evaluatedAt` (ISO-8601) as the
clock for accepted-risk expiry. Every step along the way — `architecture.resolved` (carrying the
resolver's full `ResolutionReport`, dispatched right before the unchanged `capability.resolved`),
`plugin.booting` (vetoable via an `InterceptionSlot`), `plugin.booted`, `kernel.booted` — fires
on the wired event dispatcher for observability or feature-flag plugins to hook into.

## Composes the family

`milpa/runtime` doesn't reimplement anything the family already ships — it wires the pieces
together and adds the boot sequence on top:

| Package | Owns |
|---------|------|
| `milpa/core` | Contracts (`PluginInterface`, `PluginMetadata`, events) the whole family builds on. |
| `milpa/container` | The DI container every plugin and controller is resolved through. |
| `milpa/events` | The dispatcher every lifecycle event (`plugin.booting`/`plugin.booted`, `architecture.resolved`, `capability.resolved`, `kernel.booted`) fires on. |
| `milpa/http` | Routing contracts — `Route`, `RouteResult`, `RouterInterface` — the route table is built from. |
| `milpa/resolver` | The pre-boot architecture gate AND the boot order — `AttributeLoader` ingests each plugin's `#[PluginMetadata]`, `GraphResolver` resolves the whole graph into the `ResolutionReport` that `architecture.resolved` carries, and that report's `loadOrder[]` is the sequence the boot loop follows. |
| **`milpa/runtime`** (this package) | **`Kernel::boot()`** itself: the wiring, the pre-boot architecture resolution call, the ordered boot loop with lifecycle events, and `Router`/`RequestHandler` — a minimal `RouterInterface` implementation and PSR-15 entry point over the assembled route table. |

## Requirements

- PHP **≥ 8.3**
- [`milpa/core`](https://packagist.org/packages/milpa/core) **^0.5.2**
- [`milpa/command`](https://packagist.org/packages/milpa/command) **^0.1**
- [`milpa/container`](https://packagist.org/packages/milpa/container) **^0.1**
- [`milpa/events`](https://packagist.org/packages/milpa/events) **^0.2**
- [`milpa/http`](https://packagist.org/packages/milpa/http) **^0.1**
- [`milpa/resolver`](https://packagist.org/packages/milpa/resolver) **^0.3**

## Documentation

**Full API reference: [getmilpa.github.io/runtime](https://getmilpa.github.io/runtime/)** — generated
straight from the source DocBlocks and dressed with the Milpa design system.

## Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Please report security
issues via [SECURITY.md](SECURITY.md), and note that this project follows a
[Code of Conduct](CODE_OF_CONDUCT.md).

## License

[Apache-2.0](LICENSE) © TeamX Agency.

---

Milpa is designed, built, and maintained by **[TeamX Agency](https://teamx.agency/?utm_source=github&utm_medium=readme&utm_campaign=milpa&utm_content=runtime)**.
