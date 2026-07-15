<?php

namespace Laravel\Doctor\Support;

use Illuminate\Support\Composer as IlluminateComposer;

final class Composer
{
    /**
     * Get the Composer command for the application.
     *
     * @return list<string>
     */
    public static function command(): array
    {
        /** @var IlluminateComposer $composer */
        $composer = app('composer');

        return array_values($composer->findComposer());
    }
}
