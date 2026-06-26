<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;

class DebugModeMatchesEnvironment extends Diagnostic
{
    public string $name = 'Debug mode matches environment';

    public string $group = 'security';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'enabled-in-production' => Outcome::fail(
                summary: 'Laravel debug mode is enabled in production.',
                remediation: 'Set APP_DEBUG=false in production.',
            ),
            'matches' => Outcome::pass('Laravel debug mode matches the application environment.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if ($this->debugIsEnabledInProduction()) {
            return $this->result('enabled-in-production');
        }

        return $this->result('matches');
    }

    /**
     * Determine whether debug mode is enabled in production.
     */
    private function debugIsEnabledInProduction(): bool
    {
        return (bool) config('app.debug') && app()->environment('production');
    }
}
