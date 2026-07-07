<?php

namespace Laravel\Doctor;

use InvalidArgumentException;

enum EnvironmentMode: string
{
    case Local = 'local';
    case Production = 'production';

    /**
     * Resolve the current Doctor environment mode.
     */
    public static function current(): self
    {
        return self::fromLaravelEnvironment((string) app()->environment());
    }

    /**
     * Resolve a Doctor environment mode from a Laravel environment name.
     */
    public static function fromLaravelEnvironment(string $environment): self
    {
        $mode = config()->array('doctor.environments', [])[$environment] ?? null;

        if ($mode === null) {
            return self::Production;
        }

        $resolved = is_string($mode) ? self::tryFrom($mode) : null;

        if ($resolved === null) {
            throw new InvalidArgumentException(sprintf(
                'Invalid Doctor environment mode [%s] configured for the [%s] environment.',
                is_string($mode) ? $mode : get_debug_type($mode),
                $environment,
            ));
        }

        return $resolved;
    }

    /**
     * Determine whether this mode should use production expectations.
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}
