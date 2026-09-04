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
 * One environment variable a service reads, and where its value comes from.
 *
 * A plugin can say the value outright (`value`), point at the app config key that holds it
 * (`configKey` — the app owns it, the projection reads it), or mark it secret: a secret is never
 * shown and never inlined; it is projected as `${NAME}` so the operator supplies it out of band.
 *
 * When both a literal and a config key are given, the app's config wins and the literal is the
 * fallback. A secret NEVER carries a literal value: a declaration that says «secret» and hands the
 * value over in code is lying, and the contract refuses it here rather than trusting every reader
 * to mask it downstream.
 */
final readonly class EnvVar
{
    public const NAME_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

    /**
     * @param string      $name      the variable as the container reads it (`^[A-Z][A-Z0-9_]*$`)
     * @param string|null $value     a literal value, when the plugin can say it
     * @param string|null $configKey the dotted app config key the value lives under, when the app owns it
     * @param bool        $secret    never shown, never inlined — projected as `${NAME}`
     */
    public function __construct(
        public string $name,
        public ?string $value = null,
        public ?string $configKey = null,
        public bool $secret = false,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Env var name «%s» must match %s.', $name, self::NAME_PATTERN));
        }
        if ($secret && $value !== null) {
            throw new \InvalidArgumentException(\sprintf(
                'Env var «%s» is declared secret but carries a literal value: a secret is supplied by the operator, never by the plugin.',
                $name,
            ));
        }
    }
}
