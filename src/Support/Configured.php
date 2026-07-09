<?php

namespace Laravel\Doctor\Support;

final class Configured
{
    /**
     * Get a configured string value, treating blank or non-string values as missing.
     *
     * Unlike the config repository's string() method, this never throws on an
     * unexpected type, since diagnostics must be able to inspect misconfigured
     * applications without failing before they can report.
     *
     * @return ($default is string ? string : string|null)
     */
    public static function string(string $key, ?string $default = null): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
