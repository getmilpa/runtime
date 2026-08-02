# Changelog

## [0.7.4](https://github.com/getmilpa/runtime/compare/v0.7.3...v0.7.4) (2026-08-02)


### Bug Fixes

* widen milpa/command and milpa/plugin pins to accept the 0.5/0.8 minors ([3088f2c](https://github.com/getmilpa/runtime/commit/3088f2c66a397088af039969245f4bf44ec59062))

## [0.7.3](https://github.com/getmilpa/runtime/compare/v0.7.2...v0.7.3) (2026-08-01)


### Bug Fixes

* **deps:** el pin de milpa/command deja de ser una jaula de un minor ([228e710](https://github.com/getmilpa/runtime/commit/228e71087bf36bbf5fcf041b89887c26ba715613))

## [0.7.2](https://github.com/getmilpa/runtime/compare/v0.7.1...v0.7.2) (2026-08-01)


### Bug Fixes

* **deps:** el pin de milpa/core acepta la linea 0.7 ([1a992e6](https://github.com/getmilpa/runtime/commit/1a992e6fb39d67a920910dbef18b34390e30a002))

## [0.7.1](https://github.com/getmilpa/runtime/compare/v0.7.0...v0.7.1) (2026-07-31)


### Bug Fixes

* require milpa/plugin ^0.6 ([347b255](https://github.com/getmilpa/runtime/commit/347b2557a38bcc81abb81d6ce198a64f1873f09e))

## [0.7.0](https://github.com/getmilpa/runtime/compare/v0.6.0...v0.7.0) (2026-07-31)


### Features

* PluginsManagerBootStrategy collects the commands booted plugins declare ([952fa36](https://github.com/getmilpa/runtime/commit/952fa36f30bf79920272abf9201a5b7d5589dfd0))

## [0.6.0](https://github.com/getmilpa/runtime/compare/v0.5.1...v0.6.0) (2026-07-30)


### ⚠ BREAKING CHANGES

* the constraint on `milpa/command` moves from `^0.1 || ^0.2` to `^0.3`, so this package can no longer be installed alongside command 0.1 or 0.2.
* se eliminan Milpa\Runtime\Events\CapabilityResolvedEvent, KernelBootedEvent, PluginBootedEvent y PluginBootingEvent —usa las de Milpa\Events, que son las que el kernel emite de verdad— y Milpa\Runtime\Http\Router, reemplazada por Milpa\Http\Routing\Router.

### Features

* la costura de estrategias de boot — el Kernel delega la fase de plugins ([2264006](https://github.com/getmilpa/runtime/commit/2264006af4ee9d28c98bef9f8af56313635ba3cf))
* quitar los duplicados muertos de eventos y router ([fc28c97](https://github.com/getmilpa/runtime/commit/fc28c97ddeefee768e1e6298f43d59b0d70fcf27))
* require milpa/command ^0.3 (and milpa/plugin ^0.4 in dev) ([9d9126d](https://github.com/getmilpa/runtime/commit/9d9126da9d631765d9bc9bbc84603948b844e04f))

## [0.5.1](https://github.com/getmilpa/runtime/compare/v0.5.0...v0.5.1) (2026-07-26)


### Bug Fixes

* **deps:** accept milpa/command ^0.2 alongside ^0.1 ([6eb9d52](https://github.com/getmilpa/runtime/commit/6eb9d52e36dd229f65c016238ffb801fa1b39366))

## [0.5.0](https://github.com/getmilpa/runtime/compare/v0.4.2...v0.5.0) (2026-07-14)


### ⚠ BREAKING CHANGES

* the middleware pipeline runs — Route middleware dispatches through the container resolver

### Features

* the middleware pipeline runs — Route middleware dispatches through the container resolver ([0d6e513](https://github.com/getmilpa/runtime/commit/0d6e5133c619fb6f9fdf0152a9983bc108da7fa4))

## [0.4.2](https://github.com/getmilpa/runtime/compare/v0.4.1...v0.4.2) (2026-07-13)


### Bug Fixes

* rich requires records parse at the Kernel seam — no raw TypeError ([fe4979f](https://github.com/getmilpa/runtime/commit/fe4979f68b92515ab947d56c18fe937d6eccdb66))

## [0.4.1](https://github.com/getmilpa/runtime/compare/v0.4.0...v0.4.1) (2026-07-12)


### Bug Fixes

* receive milpa/core 0.6 and milpa/resolver 0.4 — pin bumps ([360310a](https://github.com/getmilpa/runtime/commit/360310ae56ba87efd204424381030b65863b43e0))

## [0.4.0](https://github.com/getmilpa/runtime/compare/v0.3.1...v0.4.0) (2026-07-12)


### ⚠ BREAKING CHANGES

* Kernel boots in the report's loadOrder — ArchitectureBlockedException carries the ResolutionReport

### Features

* Kernel boots in the report's loadOrder — ArchitectureBlockedException carries the ResolutionReport ([478edd4](https://github.com/getmilpa/runtime/commit/478edd4efa036255ab03aa59e563cf7ddc6e7680))

## [0.3.1](https://github.com/getmilpa/runtime/compare/v0.3.0...v0.3.1) (2026-07-12)


### Features

* Kernel boots through the ArchitectureResolver — learnable boot failures ([2bc5ac2](https://github.com/getmilpa/runtime/commit/2bc5ac220770e25e58ce14e9541c7766ec18f8bb))

## [0.3.0](https://github.com/getmilpa/runtime/compare/v0.2.0...v0.3.0) (2026-07-10)


### Features

* discover CommandProvider operations; CommandDefinition is now an Operation ([f867396](https://github.com/getmilpa/runtime/commit/f867396ffb10ff96c8a2dbb1e3cff905611738c1))


### Bug Fixes

* add a summary line to CommandDefinition's docblock ([8055453](https://github.com/getmilpa/runtime/commit/805545333667c1681ad4ee37d1e4bcfd864c2478))


### Miscellaneous Chores

* release 0.3.0 ([6f17b7f](https://github.com/getmilpa/runtime/commit/6f17b7fa71206ec0ceb1bfc1382e76f18eef3cae))

## [0.2.0](https://github.com/getmilpa/runtime/compare/v0.1.1...v0.2.0) (2026-07-09)


### Features

* CommandProviderInterface — plugins declare their own commands ([03fd82f](https://github.com/getmilpa/runtime/commit/03fd82fc47cb92421cd030a0a00505ca8421cc2d))


### Miscellaneous Chores

* release 0.2.0 ([117f776](https://github.com/getmilpa/runtime/commit/117f776626419b466073396778db7c7f84e74a7e))

## [0.1.1](https://github.com/getmilpa/runtime/compare/v0.1.0...v0.1.1) (2026-07-09)


### Miscellaneous Chores

* release 0.1.1 ([c46494e](https://github.com/getmilpa/runtime/commit/c46494e0aaddffd4d091c8af0ee95d9e2e6b7f06))

## 0.1.0 (2026-07-08)


### Features

* milpa/runtime initial public release ([4d9dd0d](https://github.com/getmilpa/runtime/commit/4d9dd0df5e85cf4a53ac322e4a40ffe1431ad128))


### Miscellaneous Chores

* release 0.1.0 ([16c7a23](https://github.com/getmilpa/runtime/commit/16c7a2309f72a686742523e622988627aa1b2995))
