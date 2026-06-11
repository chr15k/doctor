<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use RuntimeException;

class StorageIsWritable extends Diagnostic implements Fixable
{
    public string $name = 'Storage is writable';

    public string $group = 'storage';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $failures = $this->failures();

        if ($failures === []) {
            return DiagnosticResult::pass('Laravel can write to the required storage directories.');
        }

        return DiagnosticResult::fail('Laravel cannot write to every required storage directory.')
            ->withDetails($this->formatFailures($failures))
            ->confirmUsing('Would you like Doctor to make Laravel\'s storage directories writable?')
            ->suggest('Ensure storage directories and bootstrap/cache exist and are writable by the PHP process.');
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $changed = [];

        foreach ($this->paths() as $relative => $path) {
            if (! is_dir($path)) {
                if (! @mkdir($path, 0775, true) && ! is_dir($path)) {
                    throw new RuntimeException(sprintf('Unable to create [%s].', $relative));
                }

                $changed[] = sprintf('Created %s.', $relative);
            }

            if (! @chmod($path, 0775)) {
                throw new RuntimeException(sprintf('Unable to change permissions for [%s].', $relative));
            }
        }

        $failures = $this->failures();

        if ($failures !== []) {
            return FixResult::fail('Some Laravel storage directories are still not writable.')
                ->withDetails($this->formatFailures($failures));
        }

        if ($changed === []) {
            return FixResult::pass('Laravel storage directories are writable.');
        }

        return FixResult::pass('Laravel storage directories are writable.')
            ->withDetails(implode(PHP_EOL, $changed));
    }

    /**
     * @return array<string, string>
     */
    private function paths(): array
    {
        return [
            'storage/' => base_path('storage'),
            'storage/app/' => base_path('storage/app'),
            'storage/framework/cache/' => base_path('storage/framework/cache'),
            'storage/framework/sessions/' => base_path('storage/framework/sessions'),
            'storage/framework/views/' => base_path('storage/framework/views'),
            'storage/logs/' => base_path('storage/logs'),
            'bootstrap/cache/' => base_path('bootstrap/cache'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function failures(): array
    {
        $failures = [];

        foreach ($this->paths() as $relative => $path) {
            if (! is_dir($path)) {
                $failures[$relative] = 'directory does not exist';

                continue;
            }

            if (! $this->canWriteToDirectory($path)) {
                $failures[$relative] = 'directory is not writable';
            }
        }

        return $failures;
    }

    /**
     * @param  array<string, string>  $failures
     */
    private function formatFailures(array $failures): string
    {
        $formatted = [];

        foreach ($failures as $relative => $reason) {
            $formatted[] = sprintf('- %s (%s)', $relative, $reason);
        }

        return implode(PHP_EOL, $formatted);
    }

    private function canWriteToDirectory(string $path): bool
    {
        $file = $path.DIRECTORY_SEPARATOR.'.doctor-write-test-'.str_replace('.', '', uniqid('', true));

        $handle = @fopen($file, 'x');

        if ($handle === false) {
            return false;
        }

        fclose($handle);

        return @unlink($file);
    }
}
