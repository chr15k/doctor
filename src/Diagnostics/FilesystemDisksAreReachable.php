<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Storage;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use RuntimeException;
use Throwable;

class FilesystemDisksAreReachable extends Diagnostic
{
    public string $name = 'Filesystem disks are reachable';

    public string $group = 'storage';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $failures = [];

        foreach ($this->disks() as $disk => $configuration) {
            try {
                $this->probe($disk, $configuration);
            } catch (Throwable $e) {
                $failures[$disk] = $e->getMessage();
            }
        }

        if ($failures !== []) {
            return DiagnosticResult::fail('Laravel cannot reach every configured filesystem disk.')
                ->withDetails($this->formatFailures($failures))
                ->suggest('Check filesystem disk roots, credentials, and network access.');
        }

        return DiagnosticResult::pass('Laravel can reach every configured filesystem disk.');
    }

    /**
     * Probe a filesystem disk.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function probe(string $disk, array $configuration): void
    {
        if (($configuration['driver'] ?? null) === 'local') {
            $this->probeLocalDisk($configuration);

            return;
        }

        Storage::disk($disk)->exists('.laravel-doctor');
    }

    /**
     * Probe a local filesystem disk.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function probeLocalDisk(array $configuration): void
    {
        $root = $configuration['root'] ?? null;

        if (! is_string($root) || $root === '') {
            throw new RuntimeException('The local disk root is not configured.');
        }

        if (! is_dir($root) || ! is_readable($root)) {
            throw new RuntimeException(sprintf('The local disk root [%s] is not readable.', $root));
        }
    }

    /**
     * Get configured filesystem disks.
     *
     * @return array<string, array<string, mixed>>
     */
    private function disks(): array
    {
        $disks = config('filesystems.disks');

        if (! is_array($disks)) {
            return [];
        }

        $configured = [];

        foreach ($disks as $disk => $configuration) {
            if (is_string($disk) && is_array($configuration)) {
                $configured[$disk] = $configuration;
            }
        }

        return $configured;
    }

    /**
     * Format disk failures.
     *
     * @param  array<string, string>  $failures
     */
    private function formatFailures(array $failures): string
    {
        return implode(PHP_EOL, array_map(
            static fn (string $disk, string $message): string => sprintf('- %s: %s', $disk, $message),
            array_keys($failures),
            $failures,
        ));
    }
}
