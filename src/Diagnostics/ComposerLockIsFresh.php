<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\Details;

class ComposerLockIsFresh extends Diagnostic
{
    public string $name = 'composer.lock is fresh';

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
            'inspection-failed' => Outcome::fail(
                summary: 'Composer could not verify composer.lock freshness.',
                remediation: 'Run `composer validate --check-lock` and resolve the reported Composer errors.',
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

        $process = Process::path(base_path())->run([
            'composer',
            'validate',
            '--check-lock',
            '--no-check-publish',
            '--no-check-all',
            '--no-interaction',
        ]);

        if ($process->successful()) {
            return $this->result('fresh');
        }

        $details = Details::processOutput(
            $process->output(),
            $process->errorOutput(),
            'Composer exited without lock file details.',
        );

        if ($this->reportsStaleLock($details)) {
            return $this->result('stale')
                ->withDetails($details);
        }

        return $this->result('inspection-failed')
            ->withDetails($details);
    }

    /**
     * Determine whether Composer reported a lock file freshness problem.
     */
    private function reportsStaleLock(string $details): bool
    {
        return str_contains($details, '# Lock file errors')
            || str_contains($details, 'lock file is not up to date')
            || str_contains($details, 'not present in the lock file');
    }
}
