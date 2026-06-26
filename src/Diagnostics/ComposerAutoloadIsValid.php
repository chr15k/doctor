<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixOutcome;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Outcome;

class ComposerAutoloadIsValid extends Diagnostic implements Fixable
{
    public string $name = 'Composer autoload is valid';

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
            'dependencies-missing' => Outcome::skip('Composer dependencies are not installed.'),
            'valid' => Outcome::pass('Composer can generate optimized autoload files.'),
            'invalid' => Outcome::fail(
                summary: 'Composer cannot generate valid autoload files.',
                remediation: 'Regenerate Composer autoload files with `composer dump-autoload`.',
                confirmation: 'Would you like Doctor to regenerate Composer autoload files using `composer dump-autoload`?',
            ),
        ];
    }

    /**
     * Get the diagnostic's named fix outcome definitions.
     *
     * @return array<string, FixOutcome>
     */
    protected function fixOutcomes(): array
    {
        return [
            'regeneration-failed' => FixOutcome::fail('Composer autoload files could not be regenerated.'),
            'regenerated' => FixOutcome::pass('Composer autoload files were regenerated.'),
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

        if (! is_file(base_path('vendor/autoload.php'))) {
            return $this->result('dependencies-missing');
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
            return $this->result('valid');
        }

        return $this->result('invalid')
            ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()));
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
            return $this->fixResult('regeneration-failed')
                ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()));
        }

        return $this->fixResult('regenerated');
    }
}
