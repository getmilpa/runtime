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

namespace Milpa\Runtime\Tests\Boot;

use Milpa\Container\DIContainer;
use Milpa\Eventing\EventDispatcher;
use Milpa\Interfaces\Plugin\PluginsManagerInterface;
use Milpa\Plugin\Contracts\PluginRecord;
use Milpa\Plugin\Registry\InMemoryPluginRegistry;
use Milpa\Plugin\Runtime\ManagerConfig;
use Milpa\Plugin\Runtime\PluginsManager;
use Milpa\Runtime\Boot\BootContext;
use Milpa\Runtime\Boot\PluginsManagerBootStrategy;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class PluginsManagerBootStrategyTest extends TestCase
{
    private string $tmpDir;
    private string $pluginShortName;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/milpa-runtime-pmbs-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/cache', 0777, true);

        // Un plugin mínimo REAL en disco: FQCN único por proceso para no chocar con
        // redeclaraciones entre tests (lección 6b: class_exists guard / FQCN único).
        //
        // El suffix va ANTES del sufijo literal "Plugin": PluginsManager::scanPluginsPath()
        // solo reconoce archivos cuyo nombre TERMINA en literal "Plugin.php" (su RegexIterator
        // usa '/^.+Plugin\.php$/i') — un nombre "StubPlugin{suffix}.php" no calza (el suffix
        // queda después de "Plugin"), así que el nombre único vive en medio: "Stub{suffix}Plugin".
        $suffix = 'S' . bin2hex(random_bytes(4));
        $this->pluginShortName = "Stub{$suffix}Plugin";
        $dir = $this->tmpDir . "/plugins/Stub{$suffix}Plugin";
        mkdir($dir, 0777, true);
        $file = $dir . "/Stub{$suffix}Plugin.php";
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);

            namespace Milpa\\Plugins\\Stub{$suffix}Plugin;

            use Milpa\\Attributes\\PluginMetadata;
            use Milpa\\Command\\CommandProvider;
            use Milpa\\Command\\Operation;
            use Milpa\\Interfaces\\Plugin\\PluginInterface;
            use Milpa\\Plugin\\PluginBase;

            #[PluginMetadata(version: '1.0.0', author: 'Test', site: 'https://example.com', name: 'Stub{$suffix}Plugin', type: 'Service')]
            class Stub{$suffix}Plugin extends PluginBase implements CommandProvider, PluginInterface
            {
                public function boot(): void {}
                public function install(): void {}
                public function uninstall(): void {}
                public function enable(): void {}
                public function disable(): void {}

                /** @return list<Operation> */
                public function operations(): array
                {
                    return [new Operation(
                        name: 'stub.ping',
                        description: 'Un átomo declarado por un plugin, para medir si llega.',
                        handler: static fn (): string => 'pong',
                    )];
                }
            }
            PHP);

        // PluginsManager::scanPlugin() gates on class_exists($className) BEFORE reflecting it
        // (no autoloader maps this temp-dir namespace), mirroring milpa/plugin's own fixture
        // tests (e.g. PluginsManagerFreshPathTest::writeDependencyFixture()): the file must be
        // require_once'd right after being written, not left for Composer's PSR-4 autoloader.
        require_once $file;
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->tmpDir);
    }

    public function testDelegatesTheWholePhaseToPluginsManager(): void
    {
        $container = new DIContainer();
        // PluginsManager::__construct() resolves its logger eagerly from the container
        // (`$this->container->get(LoggerInterface::class)`); DIContainer cannot auto-wire an
        // interface, so a concrete logger must be registered before construction (mirrors
        // milpa/plugin's own PluginsManagerSmokeTest, which stubs the same lookup on a mock
        // container).
        $container->registerService(LoggerInterface::class, new NullLogger());

        $shortName = $this->pluginShortName;
        $registry = new InMemoryPluginRegistry();
        $registry->register(new PluginRecord(
            name: $shortName,
            version: '1.0.0',
            author: 'Test',
            site: 'https://example.com',
            type: 'Service',
            installed: true,
            enabled: true,
        ));
        $manager = new PluginsManager($container, $registry, new ManagerConfig(
            cacheDir: $this->tmpDir . '/cache',
            hostManifestPath: null,
            devMode: true,
            environment: 'CLI',
        ));

        $strategy = new PluginsManagerBootStrategy($manager, $this->tmpDir . '/plugins');
        $result = $strategy->bootPlugins(new BootContext(
            container: $container,
            dispatcher: new EventDispatcher(new NullLogger()),
            root: $this->tmpDir,
            config: [],
            toolRegistry: null,
        ));

        self::assertSame([$shortName], $result->bootedPluginNames);
        self::assertCount(1, $result->plugins);
        self::assertSame([], $result->routes, 'las rutas del host se ensamblan fuera del kernel (D4)');
        self::assertTrue($container->has(PluginsManagerInterface::class));
        self::assertSame($manager, $container->get(PluginsManagerInterface::class));
    }

    /**
     * El corte de P14.1a: un plugin que declara operaciones las ve llegar a la mesa de comandos
     * del kernel también por ESTA estrategia.
     *
     * Antes no llegaban, y el docblock de la clase lo enunciaba como decisión —"routes and
     * commands are intentionally NOT collected"— justificando sólo las rutas. El precio fue
     * medible: las siete operaciones de administración de plugins que `milpa/plugin` publica no
     * eran alcanzables desde ningún host montado sobre un `PluginsManager`.
     *
     * La prueba mira `$result->commands` y no una superficie: qué hace un projector con el átomo
     * es asunto del projector (ADR-0035). Lo que aquí se mide es que el átomo EXISTE.
     */
    public function testCollectsOperationsFromACommandProviderPlugin(): void
    {
        $container = new DIContainer();
        $container->registerService(LoggerInterface::class, new NullLogger());

        $registry = new InMemoryPluginRegistry();
        $registry->register(new PluginRecord(
            name: $this->pluginShortName,
            version: '1.0.0',
            author: 'Test',
            site: 'https://example.com',
            type: 'Service',
            installed: true,
            enabled: true,
        ));
        $manager = new PluginsManager($container, $registry, new ManagerConfig(
            cacheDir: $this->tmpDir . '/cache',
            hostManifestPath: null,
            devMode: true,
            environment: 'CLI',
        ));

        $strategy = new PluginsManagerBootStrategy($manager, $this->tmpDir . '/plugins');
        $result = $strategy->bootPlugins(new BootContext(
            container: $container,
            dispatcher: new EventDispatcher(new NullLogger()),
            root: $this->tmpDir,
            config: [],
            toolRegistry: null,
        ));

        self::assertCount(1, $result->commands);
        self::assertSame('stub.ping', $result->commands[0]->name);
    }
}
