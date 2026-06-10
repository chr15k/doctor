<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

class ApplicationKeyIsSet extends Diagnostic implements Fixable
{
    public string $name = 'Application key is set';

    public string $group = 'environment';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $key = config('app.key');

        if (is_string($key) && trim($key) !== '') {
            return DiagnosticResult::pass('Laravel has an application key.');
        }

        return DiagnosticResult::fail('Laravel does not have an application key.')
            ->confirmUsing('Would you like Doctor to generate an application key using `artisan key:generate`?')
            ->suggest('Generate an application key with `php artisan key:generate`.');
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $process = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'key:generate']);

        if (! $process->successful()) {
            return FixResult::fail('The application key could not be generated.')
                ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()));
        }

        return FixResult::pass('The application key was generated.');
    }
}
