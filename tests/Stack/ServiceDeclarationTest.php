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

namespace Milpa\Runtime\Tests\Stack;

use Milpa\Runtime\Stack\EnvVar;
use Milpa\Runtime\Stack\PortMapping;
use Milpa\Runtime\Stack\ServiceDeclaration;
use PHPUnit\Framework\TestCase;

final class ServiceDeclarationTest extends TestCase
{
    public function testADeclarationCarriesWhatAComposeFileAndAProbeNeed(): void
    {
        $service = new ServiceDeclaration(
            name: 'mercure',
            image: 'dunglas/mercure',
            ports: [new PortMapping(container: 80, host: 3000), new PortMapping(container: 443)],
            env: [new EnvVar('SERVER_NAME', value: ':80'), new EnvVar('MERCURE_PUBLISHER_JWT_KEY', configKey: 'desktop.mercure.publisher_key', secret: true)],
            volumes: ['mercure_data:/data'],
            command: ['caddy', 'run'],
            summary: 'The live feed of the Desktop.',
        );

        self::assertSame('mercure', $service->name);
        self::assertSame(3000, $service->probePort(), 'the first published port is what a probe tries');
        self::assertSame('3000:80', $service->ports[0]->toCompose());
        self::assertSame('443', $service->ports[1]->toCompose());
        self::assertTrue($service->env[1]->secret);
        self::assertSame('desktop.mercure.publisher_key', $service->env[1]->configKey);
    }

    public function testTheProbePortIsTheDeclaredHealthPortWhenGivenAndNullWhenNothingIsPublished(): void
    {
        $withHealth = new ServiceDeclaration(name: 'db', image: 'postgres:16', ports: [new PortMapping(5432, 5433)], healthPort: 9999);
        self::assertSame(9999, $withHealth->probePort());

        $internal = new ServiceDeclaration(name: 'cache', image: 'redis:7', ports: [new PortMapping(6379)]);
        self::assertNull($internal->probePort());

        self::assertNull((new ServiceDeclaration(name: 'bare', image: 'busybox'))->probePort());
    }

    public function testUdpMappingsSayItInCompose(): void
    {
        self::assertSame('5353:53/udp', (new PortMapping(53, 5353, 'udp'))->toCompose());
        self::assertSame('53/udp', (new PortMapping(53, null, 'udp'))->toCompose());
    }

    /**
     * @param callable(): mixed $build
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid')]
    public function testInvalidDeclarationsAreRefused(callable $build, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $build();
    }

    /** @return iterable<string, array{callable(): mixed, string}> */
    public static function invalid(): iterable
    {
        yield 'name with spaces' => [static fn () => new ServiceDeclaration(name: 'my hub', image: 'x'), 'must match'];
        yield 'uppercase name' => [static fn () => new ServiceDeclaration(name: 'Hub', image: 'x'), 'must match'];
        yield 'empty image' => [static fn () => new ServiceDeclaration(name: 'hub', image: '  '), 'names no image'];
        yield 'port not a mapping' => [static fn () => new ServiceDeclaration(name: 'hub', image: 'x', ports: [80]), 'every port must be'];
        yield 'env not an EnvVar' => [static fn () => new ServiceDeclaration(name: 'hub', image: 'x', env: ['A=1']), 'every env entry must be'];
        yield 'empty volume' => [static fn () => new ServiceDeclaration(name: 'hub', image: 'x', volumes: ['']), 'non-empty strings'];
        yield 'health port out of range' => [static fn () => new ServiceDeclaration(name: 'hub', image: 'x', healthPort: 70000), 'out of range'];
        yield 'port out of range' => [static fn () => new PortMapping(0), 'out of range'];
        yield 'host port out of range' => [static fn () => new PortMapping(80, 65536), 'out of range'];
        yield 'bad protocol' => [static fn () => new PortMapping(80, 80, 'sctp'), 'tcp or udp'];
        yield 'env name lowercase' => [static fn () => new EnvVar('server_name'), 'must match'];
        yield 'env name with dash' => [static fn () => new EnvVar('SERVER-NAME'), 'must match'];
    }
}
