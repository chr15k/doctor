<?php

namespace Laravel\Doctor;

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

        return is_string($mode)
            ? (self::tryFrom($mode) ?? self::Production)
            : self::Production;
    }

    /**
     * Determine whether this mode should use production expectations.
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}
