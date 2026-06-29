<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\File;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
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
            'generated' => Outcome::pass('The application key was generated.'),
            'generation-failed' => Outcome::fail('The application key could not be generated.'),
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
        $key = $this->generateRandomKey();

        if (! $this->writeKeyToEnvironmentFile($key)) {
            return $this->fixResult('generation-failed')
                ->withDetails(sprintf('The application environment file [%s] could not be updated.', $this->environmentFilePath()));
        }

        config(['app.key' => $key]);

        return $this->fixResult('generated');
    }

    private function generateRandomKey(): string
    {
        return 'base64:'.base64_encode(
            Encrypter::generateKey($this->applicationCipher())
        );
    }

    private function applicationCipher(): string
    {
        $cipher = config('app.cipher');

        if (! is_string($cipher) || $cipher === '') {
            $cipher = 'AES-256-CBC';
        }

        return $cipher;
    }

    private function writeKeyToEnvironmentFile(string $key): bool
    {
        $path = $this->environmentFilePath();

        if (! File::isFile($path) || ! is_writable($path)) {
            return false;
        }

        $contents = File::get($path);
        $line = 'APP_KEY='.$key;
        $updated = $this->replaceExistingKey($contents, $line)
            ?? $this->appendMissingKey($contents, $line);

        return File::put($path, $updated) !== false;
    }

    private function replaceExistingKey(string $contents, string $line): ?string
    {
        if (preg_match('/^\s*APP_KEY\s*=.*$/m', $contents) !== 1) {
            return null;
        }

        $updated = preg_replace('/^\s*APP_KEY\s*=.*$/m', $line, $contents, 1);

        return is_string($updated) ? $updated : null;
    }

    private function appendMissingKey(string $contents, string $line): string
    {
        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $separator = $contents === '' || str_ends_with($contents, "\n") ? '' : $newline;

        return $contents.$separator.$line.$newline;
    }

    private function environmentFilePath(): string
    {
        return app()->environmentFilePath();
    }
}
