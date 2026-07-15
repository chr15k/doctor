<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Env;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\EnvironmentMode;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;

class ApplicationKeyIsSet extends Diagnostic implements Fixable
{
    public string $name = 'App key is set';

    public string $group = 'environment';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'configured' => 'The application key is configured.',
            'missing' => Message::make(
                summary: 'The application key is not configured.',
                remediation: 'Generate an application key with `php artisan key:generate`.',
                confirmation: 'Generate an application key?',
            )->link(Link::docs('encryption')),
            'generated' => 'The application key was generated.',
            'generation-failed' => 'The application key could not be generated.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if ($this->keyIsConfigured()) {
            return $this->pass('configured');
        }

        return $this->fail('missing')->fixable(EnvironmentMode::Local);
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $path = app()->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            return $this->fixFailed('generation-failed')
                ->withDetails(sprintf('The application environment file [%s] could not be updated.', $path));
        }

        $cipher = config('app.cipher');

        if (! is_string($cipher) || $cipher === '') {
            return $this->fixFailed('generation-failed')
                ->withDetails('The application encryption cipher is not configured.');
        }

        $key = 'base64:'.base64_encode(Encrypter::generateKey($cipher));

        Env::writeVariable('APP_KEY', $key, $path, overwrite: true);

        config(['app.key' => $key]);

        return $this->fixed('generated');
    }

    /**
     * Determine whether the application key is configured.
     */
    private function keyIsConfigured(): bool
    {
        $key = config('app.key');

        return is_string($key) && trim($key) !== '';
    }
}
