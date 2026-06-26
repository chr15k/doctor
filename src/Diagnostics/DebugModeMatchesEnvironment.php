<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class DebugModeMatchesEnvironment extends Diagnostic
{
    public string $name = 'Debug mode matches environment';

    public string $group = 'security';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if ($this->debugIsEnabledInProduction()) {
            return DiagnosticResult::fail('Laravel debug mode is enabled in production.')
                ->suggest('Set APP_DEBUG=false in production.');
        }

        return DiagnosticResult::pass('Laravel debug mode matches the application environment.');
    }

    /**
     * Determine whether debug mode is enabled in production.
     */
    private function debugIsEnabledInProduction(): bool
    {
        return (bool) config('app.debug') && app()->environment('production');
    }
}
