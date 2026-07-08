<?php

namespace Laravel\Doctor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Doctor\Doctor diagnostic(class-string<\Laravel\Doctor\Diagnostic> $diagnostic)
 * @method static \Laravel\Doctor\Doctor diagnostics(iterable<class-string<\Laravel\Doctor\Diagnostic>> $diagnostics)
 * @method static \Laravel\Doctor\Results\DiagnosticReport run(?\Laravel\Doctor\DiagnosticSelection $selection = null)
 * @method static \Laravel\Doctor\PendingRun runner(?\Laravel\Doctor\DiagnosticSelection $selection = null)
 * @method static \Laravel\Doctor\DiagnosticSelection defaultSelection()
 *
 * @see \Laravel\Doctor\Doctor
 */
class Doctor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laravel\Doctor\Doctor::class;
    }
}
