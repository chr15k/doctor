<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\File;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Outcome;

class EnvironmentFileExists extends Diagnostic implements Fixable
{
    public string $name = '.env file exists';

    public string $group = 'environment';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'exists' => Outcome::pass('Laravel has an environment file.'),
            'missing' => Outcome::fail(
                summary: 'Laravel does not have an environment file.',
                confirmation: 'Would you like Doctor to copy .env.example to .env?',
            ),
            'missing-with-example' => Outcome::fail(
                summary: 'Laravel does not have an environment file.',
                remediation: 'Copy the example environment file to .env, then review its values.',
                confirmation: 'Would you like Doctor to copy .env.example to .env?',
            ),
            'already-exists' => Outcome::skip('The .env file already exists.'),
            'example-missing' => Outcome::fail('The .env.example file does not exist.'),
            'creation-failed' => Outcome::fail('The .env file could not be created from .env.example.'),
            'created' => Outcome::pass('The .env file was created from .env.example.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if ($this->environmentFiles() !== []) {
            return $this->result('exists');
        }

        if (is_file(base_path('.env.example'))) {
            return $this->result('missing-with-example');
        }

        return $this->result('missing');
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $target = base_path('.env');
        $example = base_path('.env.example');

        if (is_file($target)) {
            return $this->fixResult('already-exists');
        }

        if (! is_file($example)) {
            return $this->fixResult('example-missing');
        }

        if (! @copy($example, $target)) {
            return $this->fixResult('creation-failed');
        }

        return $this->fixResult('created')
            ->withDetails('Review .env and run `php artisan key:generate` if APP_KEY is empty.');
    }

    /**
     * Get the application's environment files.
     *
     * @return list<string>
     */
    private function environmentFiles(): array
    {
        return array_values(array_filter(
            File::glob(base_path('.env*')) ?: [],
            static fn (string $file): bool => is_file($file)
                && ! str_ends_with(basename($file), '.example'),
        ));
    }
}
