<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

class EnvironmentFileExists extends Diagnostic implements Fixable
{
    public string $name = 'Environment file exists';

    public string $group = 'environment';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if ($this->environmentFiles() !== []) {
            return DiagnosticResult::pass('Laravel has an environment file.');
        }

        $result = DiagnosticResult::fail('Laravel does not have an environment file.')
            ->confirmUsing('Would you like Doctor to copy .env.example to .env?');

        if (is_file(base_path('.env.example'))) {
            return $result->suggest('Copy the example environment file to .env, then review its values.');
        }

        return $result;
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $target = base_path('.env');
        $example = base_path('.env.example');

        if (is_file($target)) {
            return FixResult::skip('The .env file already exists.');
        }

        if (! is_file($example)) {
            return FixResult::fail('The .env.example file does not exist.');
        }

        if (! @copy($example, $target)) {
            return FixResult::fail('The .env file could not be created from .env.example.');
        }

        return FixResult::pass('The .env file was created from .env.example.')
            ->withDetails('Review .env and run `php artisan key:generate` if APP_KEY is empty.');
    }

    /**
     * @return list<string>
     */
    private function environmentFiles(): array
    {
        $files = glob(base_path('.env*')) ?: [];

        return array_values(array_filter(
            $files,
            static fn (string $file): bool => is_file($file)
                && basename($file) !== '.env.example'
                && ! str_ends_with(basename($file), '.example'),
        ));
    }
}
