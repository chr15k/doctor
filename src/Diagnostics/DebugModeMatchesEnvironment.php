<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Env;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\EnvironmentMode;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;

class DebugModeMatchesEnvironment extends Diagnostic implements Fixable
{
    public string $name = 'Debug matches environment';

    public string $group = 'security';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'enabled-in-production' => Message::make(
                summary: 'Debug mode is enabled in production.',
                remediation: 'Set APP_DEBUG=false in production.',
                confirmation: 'Set APP_DEBUG=false in the application environment file?',
            )->link(Link::docs('configuration', 'debug-mode')),
            'matches' => 'Debug mode matches the application environment.',
            'already-disabled' => 'Debug mode is already disabled.',
            'disable-failed' => 'Debug mode could not be disabled in the application environment file.',
            'disabled' => 'Debug mode was disabled in the application environment file.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if ($this->debugIsEnabledInProduction()) {
            return $this->fail('enabled-in-production')->fixable();
        }

        return $this->pass('matches');
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        if (! $this->debugIsEnabledInProduction()) {
            return $this->fixed('already-disabled');
        }

        $path = app()->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            return $this->fixFailed('disable-failed')
                ->withDetails(sprintf('The application environment file [%s] could not be updated.', $path));
        }

        Env::writeVariable('APP_DEBUG', 'false', $path, overwrite: true);

        config(['app.debug' => false]);

        return $this->fixed('disabled');
    }

    /**
     * Determine whether debug mode is enabled in production.
     */
    private function debugIsEnabledInProduction(): bool
    {
        return (bool) config('app.debug') && EnvironmentMode::current()->isProduction();
    }
}
