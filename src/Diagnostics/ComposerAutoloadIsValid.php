<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Composer;

class ComposerAutoloadIsValid extends Diagnostic implements Fixable
{
    public string $name = 'Composer autoload works';

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
            'dependencies-missing' => 'Composer dependencies are not installed.',
            'valid' => 'Composer can generate optimized autoload files.',
            'invalid' => Message::make(
                summary: 'Composer cannot generate valid autoload files.',
                remediation: 'Regenerate Composer autoload files with `composer dump-autoload`.',
                confirmation: 'Regenerate Composer autoload files using `composer dump-autoload`?',
            ),
            'regeneration-failed' => 'Composer autoload files could not be regenerated.',
            'regenerated' => 'Composer autoload files were regenerated.',
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

        if (! is_file(base_path('vendor/autoload.php'))) {
            return $this->skip('dependencies-missing');
        }

        $process = Process::path(base_path())->run([
            ...Composer::command(),
            'dump-autoload',
            '--dry-run',
            '--optimize',
            '--strict-psr',
            '--strict-ambiguous',
            '--no-interaction',
        ]);

        if ($process->successful()) {
            return $this->pass('valid');
        }

        return $this->fail('invalid')
            ->fixable()
            ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()));
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        $process = Process::path(base_path())->run([
            ...Composer::command(),
            'dump-autoload',
            '--optimize',
            '--strict-psr',
            '--strict-ambiguous',
            '--no-interaction',
        ]);

        if (! $process->successful()) {
            return $this->fixFailed('regeneration-failed')
                ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()));
        }

        return $this->fixed('regenerated');
    }
}
