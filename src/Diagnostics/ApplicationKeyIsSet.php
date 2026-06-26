<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Artisan;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixOutcome;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Outcome;

class ApplicationKeyIsSet extends Diagnostic implements Fixable
{
    public string $name = 'Application key is set';

    public string $group = 'environment';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'configured' => Outcome::pass('Laravel has an application key.'),
            'missing' => Outcome::fail(
                summary: 'Laravel does not have an application key.',
                remediation: 'Generate an application key with `php artisan key:generate`.',
                confirmation: 'Would you like Doctor to generate an application key using `artisan key:generate`?',
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
            'generated' => FixOutcome::pass('The application key was generated.'),
            'generation-failed' => FixOutcome::fail('The application key could not be generated.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $key = config('app.key');

        if (is_string($key) && trim($key) !== '') {
            return $this->result('configured');
        }

        return $this->result('missing');
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $exitCode = Artisan::call('key:generate');

        if ($exitCode !== 0) {
            return $this->fixResult('generation-failed')
                ->withDetails(trim(Artisan::output()));
        }

        return $this->fixResult('generated');
    }
}
