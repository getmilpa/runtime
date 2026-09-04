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
 * A port a service listens on inside its container, and the host port it is published on, if any.
 */
final readonly class PortMapping
{
    /**
     * @param int      $container the port inside the container
     * @param int|null $host      the port on the host, or null when the service is reachable only inside the stack
     * @param string   $protocol  `tcp` or `udp`
     */
    public function __construct(
        public int $container,
        public ?int $host = null,
        public string $protocol = 'tcp',
    ) {
        foreach ([$container, $host] as $port) {
            if ($port !== null && ($port < 1 || $port > 65535)) {
                throw new \InvalidArgumentException(\sprintf('Port %d is out of range.', $port));
            }
        }
        if (!\in_array($protocol, ['tcp', 'udp'], true)) {
            throw new \InvalidArgumentException(\sprintf('Port protocol must be tcp or udp, got «%s».', $protocol));
        }
    }

    /** The mapping as a compose `ports:` entry — `host:container`, with `/udp` when it is not tcp. */
    public function toCompose(): string
    {
        $suffix = $this->protocol === 'udp' ? '/udp' : '';

        return ($this->host === null ? (string) $this->container : $this->host . ':' . $this->container) . $suffix;
    }
}
