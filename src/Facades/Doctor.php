<?php

declare(strict_types=1);

namespace Laravel\Doctor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Doctor\Doctor diagnostic(class-string<\Laravel\Doctor\Contracts\Diagnostic> $diagnostic)
 * @method static \Laravel\Doctor\Doctor diagnostics(iterable<class-string<\Laravel\Doctor\Contracts\Diagnostic>> $diagnostics)
 * @method static \Laravel\Doctor\Support\DiagnosticRegistry registry()
 * @method static \Laravel\Doctor\Results\DiagnosticReport run(?\Laravel\Doctor\Support\DiagnosticSelection $selection = null)
 * @method static \Laravel\Doctor\Support\DiagnosticRunner diagnosticRunner()
 */
class Doctor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laravel\Doctor\Doctor::class;
    }
}
