<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

class PublicStorageLinkExists extends Diagnostic implements Fixable
{
    public string $name = 'Public storage link exists';

    public string $group = 'storage';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! $this->expectsPublicStorageLink()) {
            return DiagnosticResult::skip('The public disk does not use Laravel\'s default local public storage path.');
        }

        if ($this->publicDiskIsUnused()) {
            return DiagnosticResult::skip('The public storage directory does not contain public files.');
        }

        if (file_exists($this->publicStoragePath())) {
            return DiagnosticResult::pass('The public storage link exists.');
        }

        return DiagnosticResult::fail('The public storage link does not exist.')
            ->confirmUsing('Would you like Doctor to create the public storage link using `artisan storage:link`?')
            ->suggest('Create the public storage link with `php artisan storage:link`.');
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $process = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'storage:link']);

        if (! $process->successful()) {
            return FixResult::fail('The public storage link could not be created.')
                ->withDetails(trim($process->errorOutput() !== '' ? $process->errorOutput() : $process->output()));
        }

        return FixResult::pass('The public storage link was created.');
    }

    private function expectsPublicStorageLink(): bool
    {
        $disk = config('filesystems.disks.public');

        if (! is_array($disk) || ($disk['driver'] ?? null) !== 'local') {
            return false;
        }

        $root = $disk['root'] ?? null;

        return is_string($root)
            && $this->normalize($root) === $this->normalize(base_path('storage/app/public'));
    }

    private function publicStoragePath(): string
    {
        return base_path('public/storage');
    }

    private function publicDiskIsUnused(): bool
    {
        $path = base_path('storage/app/public');

        if (! is_dir($path)) {
            return true;
        }

        $files = scandir($path);

        if ($files === false) {
            return false;
        }

        return array_values(array_diff($files, ['.', '..', '.gitignore'])) === [];
    }

    private function normalize(string $path): string
    {
        $realPath = realpath($path);

        return $realPath === false ? rtrim($path, DIRECTORY_SEPARATOR) : $realPath;
    }
}
