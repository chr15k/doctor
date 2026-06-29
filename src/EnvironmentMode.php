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
        $environment = app()->environment();

        return self::fromLaravelEnvironment(is_string($environment) ? $environment : '');
    }

    /**
     * Resolve a Doctor environment mode from a Laravel environment name.
     */
    public static function fromLaravelEnvironment(string $environment): self
    {
        $environments = config('doctor.environments', []);

        $mode = is_array($environments)
            ? ($environments[$environment] ?? self::Production->value)
            : self::Production->value;

        return is_string($mode)
            ? (self::tryFrom($mode) ?? self::Production)
            : self::Production;
    }

    /**
     * Determine whether this mode should use local development expectations.
     */
    public function isLocal(): bool
    {
        return $this === self::Local;
    }

    /**
     * Determine whether this mode should use production expectations.
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}
