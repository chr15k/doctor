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
                remediation: 'Run `composer install` to generate a composer.lock file.',
            ),
            'fresh' => 'composer.lock is present and fresh.',
            'stale' => Message::make(
                summary: 'composer.lock is out of date with composer.json.',
                remediation: 'Run `composer update --lock` to refresh the lock file.',
            ),
            'constraint-mismatch' => Message::make(
                summary: 'composer.lock does not satisfy the package constraints in composer.json.',
                remediation: 'Run `composer update {packages}` to lock versions that satisfy composer.json.',
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
            '--no-ansi',
        ]);

        if ($process->successful()) {
            return $this->pass('fresh');
        }

        $details = Details::processOutput(
            $process->output(),
            $process->errorOutput(),
            'Composer exited without lock file details.',
        );

        if (($packages = $this->mismatchedPackages($details)) !== []) {
            return $this->fail('constraint-mismatch', ['packages' => implode(' ', $packages)])
                ->withDetails($this->lockFileErrors($details) ?? $details);
        }

        if ($this->reportsStaleLock($details)) {
            return $this->fail('stale')
                ->withDetails($this->lockFileErrors($details) ?? $details);
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
            || str_contains($details, 'lock file is not up to date');
    }

    /**
     * Extract the factual lock file error lines from Composer's validation output.
     *
     * Composer intersperses its own remediation advice and documentation links
     * with the errors; only the "- " bulleted error lines state what is wrong.
     */
    private function lockFileErrors(string $details): ?string
    {
        $errors = str($details)
            ->explode(PHP_EOL)
            ->map(static fn (string $line): string => trim($line))
            ->filter(static fn (string $line): bool => str_starts_with($line, '- '))
            ->map(static fn (string $line): string => (string) str($line)->before(', it is recommended')->finish('.'))
            ->implode(PHP_EOL);

        return $errors === '' ? null : $errors;
    }

    /**
     * Get the packages whose locked versions no longer satisfy composer.json.
     *
     * @return list<string>
     */
    private function mismatchedPackages(string $details): array
    {
        preg_match_all('/Required (?:dev )?package "([^"]+)"/', $details, $matches);

        return array_values(array_unique($matches[1]));
    }
}
