<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;

class ComposerLockIsFresh extends Diagnostic
{
    public string $name = 'Composer lock file is fresh';

    public string $group = 'composer';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'composer-missing' => Outcome::skip('The application does not have a composer.json file.'),
            'lock-missing' => Outcome::fail(
                summary: 'The application does not have a composer.lock file.',
                remediation: 'Commit a composer.lock file so deployments install the same dependencies.',
            ),
            'fresh' => Outcome::pass('composer.lock is present and fresh.'),
            'stale' => Outcome::fail(
                summary: 'composer.lock is missing or out of date.',
                remediation: 'Refresh the lock file metadata without changing installed package versions.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! is_file(base_path('composer.json'))) {
            return $this->result('composer-missing');
        }

        if (! is_file(base_path('composer.lock'))) {
            return $this->result('lock-missing');
        }

        $process = Process::path(base_path())->run(['composer', 'validate', '--strict', '--no-check-publish']);

        if ($process->successful()) {
            return $this->result('fresh');
        }

        return $this->result('stale')
            ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()));
    }
}
