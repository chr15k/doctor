<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Storage;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use RuntimeException;
use Throwable;

class FilesystemDisksAreReachable extends Diagnostic
{
    public string $name = 'Filesystem disks are reachable';

    public string $group = 'storage';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-configured' => Outcome::skip('Laravel does not have a default filesystem disk configured.'),
            'disk-missing' => Outcome::skip('The default filesystem disk is not configured.'),
            'unreachable' => Outcome::fail(
                summary: 'Laravel cannot reach the default filesystem disk.',
                remediation: 'Check filesystem disk roots, credentials, and network access.',
            ),
            'reachable' => Outcome::pass('Laravel can reach the default filesystem disk.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $disk = config('filesystems.default');

        if (! is_string($disk) || $disk === '') {
            return $this->result('not-configured');
        }

        $configuration = $this->configuration($disk);

        if ($configuration === null) {
            return $this->result('disk-missing')
                ->withDetails(sprintf('The [%s] filesystem disk is not configured.', $disk));
        }

        try {
            $this->probe($disk, $configuration);
        } catch (Throwable $e) {
            return $this->result('unreachable')
                ->withDetails($e->getMessage());
        }

        return $this->result('reachable');
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
     * Get the default filesystem disk configuration.
     *
     * @return array<string, mixed>|null
     */
    private function configuration(string $disk): ?array
    {
        $configuration = config("filesystems.disks.{$disk}");

        if (! is_array($configuration)) {
            return null;
        }

        $configured = [];

        foreach ($configuration as $key => $value) {
            if (is_string($key)) {
                $configured[$key] = $value;
            }
        }

        return $configured;
    }
}
