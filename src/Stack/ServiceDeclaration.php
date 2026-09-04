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

namespace Milpa\Runtime\Stack;

/**
 * One backing service a plugin needs, as data: the image to run, the ports it listens on, the
 * environment it reads, the volumes it keeps.
 *
 * Deliberately the subset a compose file and a reachability probe need — nothing about how to
 * orchestrate it. Future fields (healthcheck commands, networks, depends_on) enter when something
 * needs them, not before.
 */
final readonly class ServiceDeclaration
{
    public const NAME_PATTERN = '/^[a-z][a-z0-9-]{0,62}$/';

    /**
     * @param string            $name       the service name, as compose would key it (`^[a-z][a-z0-9-]{0,62}$`)
     * @param string            $image      the container image, e.g. `dunglas/mercure`
     * @param list<PortMapping> $ports      the ports the service listens on, with the host port when it is published
     * @param list<EnvVar>      $env        the environment the service reads
     * @param list<string>      $volumes    compose-style `source:target[:mode]` entries, or named volumes
     * @param list<string>      $command    an explicit command, empty for the image's default
     * @param string            $summary    why the plugin needs it — one line for a human
     * @param int|null          $healthPort the host port a reachability probe should try; defaults to the first published port
     */
    public function __construct(
        public string $name,
        public string $image,
        public array $ports = [],
        public array $env = [],
        public array $volumes = [],
        public array $command = [],
        public string $summary = '',
        public ?int $healthPort = null,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Service name «%s» must match %s.', $name, self::NAME_PATTERN));
        }
        if (trim($image) === '') {
            throw new \InvalidArgumentException(\sprintf('Service «%s» names no image.', $name));
        }
        foreach ($ports as $port) {
            if (!$port instanceof PortMapping) {
                throw new \InvalidArgumentException(\sprintf('Service «%s»: every port must be a %s, got %s.', $name, PortMapping::class, get_debug_type($port)));
            }
        }
        foreach ($env as $variable) {
            if (!$variable instanceof EnvVar) {
                throw new \InvalidArgumentException(\sprintf('Service «%s»: every env entry must be an %s, got %s.', $name, EnvVar::class, get_debug_type($variable)));
            }
        }
        foreach ([...$volumes, ...$command] as $entry) {
            if (!\is_string($entry) || $entry === '') {
                throw new \InvalidArgumentException(\sprintf('Service «%s»: volumes and command entries must be non-empty strings.', $name));
            }
        }
        if ($healthPort !== null && ($healthPort < 1 || $healthPort > 65535)) {
            throw new \InvalidArgumentException(\sprintf('Service «%s»: health port %d is out of range.', $name, $healthPort));
        }
    }

    /** The host port a reachability probe should try, or null when the service publishes none. */
    public function probePort(): ?int
    {
        if ($this->healthPort !== null) {
            return $this->healthPort;
        }
        foreach ($this->ports as $port) {
            if ($port->host !== null) {
                return $port->host;
            }
        }

        return null;
    }
}
