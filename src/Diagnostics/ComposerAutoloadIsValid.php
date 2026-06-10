<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

class ComposerAutoloadIsValid extends Diagnostic implements Fixable
{
    public string $name = 'Composer autoload is valid';

    public string $group = 'composer';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! is_file(base_path('composer.json'))) {
            return DiagnosticResult::skip('The application does not have a composer.json file.');
        }

        if (! is_file(base_path('vendor/autoload.php'))) {
            return DiagnosticResult::skip('Composer dependencies are not installed.');
        }

        $process = Process::path(base_path())->run([
            'composer',
            'dump-autoload',
            '--dry-run',
            '--optimize',
            '--strict-psr',
            '--strict-ambiguous',
            '--no-interaction',
        ]);

        if ($process->successful()) {
            return DiagnosticResult::pass('Composer can generate optimized autoload files.');
        }

        return DiagnosticResult::fail('Composer cannot generate valid autoload files.')
            ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()))
            ->confirmUsing('Would you like Doctor to regenerate Composer autoload files using `composer dump-autoload`?')
            ->suggest('Regenerate Composer autoload files with `composer dump-autoload`.');
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $process = Process::path(base_path())->run([
            'composer',
            'dump-autoload',
            '--optimize',
            '--strict-psr',
            '--strict-ambiguous',
            '--no-interaction',
        ]);

        if (! $process->successful()) {
            return FixResult::fail('Composer autoload files could not be regenerated.')
                ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()));
        }

        return FixResult::pass('Composer autoload files were regenerated.');
    }
}
