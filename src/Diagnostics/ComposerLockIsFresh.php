<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Details;

class ComposerLockIsFresh extends Diagnostic
{
    public string $name = 'composer.lock is fresh';

    public string $group = 'composer';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'composer-missing' => 'The application does not have a composer.json file.',
            'lock-missing' => Message::make(
                summary: 'The application does not have a composer.lock file.',
                remediation: 'Commit a composer.lock file so deployments install the same dependencies.',
            ),
            'fresh' => 'composer.lock is present and fresh.',
            'stale' => Message::make(
                summary: 'composer.lock is missing or out of date.',
                remediation: 'Refresh the lock file metadata without changing installed package versions.',
            ),
            'inspection-failed' => Message::make(
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
            return $this->skip('composer-missing');
        }

        if (! is_file(base_path('composer.lock'))) {
            return $this->fail('lock-missing');
        }

        $process = Process::path(base_path())->run([
            ...$this->composer(),
            'validate',
            '--check-lock',
            '--no-check-publish',
            '--no-check-all',
            '--no-interaction',
        ]);

        if ($process->successful()) {
            return $this->pass('fresh');
        }

        $details = Details::processOutput(
            $process->output(),
            $process->errorOutput(),
            'Composer exited without lock file details.',
        );

        if ($this->reportsStaleLock($details)) {
            return $this->fail('stale')
                ->withDetails($details);
        }

        return $this->fail('inspection-failed')
            ->withDetails($details);
    }

    /**
     * Get the Composer command for the application.
     *
     * @return list<string>
     */
    private function composer(): array
    {
        /** @var Composer $composer */
        $composer = app('composer');

        return array_values($composer->findComposer());
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
