<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class ComposerLockIsFresh extends Diagnostic
{
    public string $name = 'Composer lock file is fresh';

    public string $group = 'composer';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! is_file(base_path('composer.json'))) {
            return DiagnosticResult::skip('The application does not have a composer.json file.');
        }

        if (! is_file(base_path('composer.lock'))) {
            return DiagnosticResult::fail('The application does not have a composer.lock file.')
                ->suggest('Commit a composer.lock file so deployments install the same dependencies.');
        }

        $process = Process::path(base_path())->run(['composer', 'validate', '--strict', '--no-check-publish']);

        if ($process->successful()) {
            return DiagnosticResult::pass('composer.lock is present and fresh.');
        }

        return DiagnosticResult::fail('composer.lock is missing or out of date.')
            ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()))
            ->suggest('Refresh the lock file metadata without changing installed package versions.');
    }
}
