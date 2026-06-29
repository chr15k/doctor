<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use RuntimeException;
use Throwable;

class QueueConnectionIsReachable extends Diagnostic
{
    public string $name = 'Queue connects';

    public string $group = 'queue';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-configured' => Outcome::skip('Laravel does not have a default queue connection configured.'),
            'unreachable' => Outcome::fail(
                summary: 'Laravel cannot reach the default queue connection.',
                remediation: 'Check QUEUE_CONNECTION and the backing queue service configuration.',
            ),
            'reachable' => Outcome::pass('Laravel can reach the default queue connection.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $connection = config('queue.default');

        if (! is_string($connection) || $connection === '') {
            return $this->result('not-configured');
        }

        try {
            $this->probe($connection, $this->configuration($connection));
        } catch (Throwable $e) {
            return $this->result('unreachable')
                ->withDetails($e->getMessage());
        }

        return $this->result('reachable');
    }

    /**
     * Probe the configured queue connection.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function probe(string $connection, array $configuration): void
    {
        match ($configuration['driver'] ?? null) {
            'sync' => null,
            'database' => $this->probeDatabaseQueue($configuration),
            'redis' => $this->probeRedisQueue($configuration),
            default => Queue::connection($connection),
        };
    }

    /**
     * Probe a database-backed queue.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function probeDatabaseQueue(array $configuration): void
    {
        $table = $configuration['table'] ?? 'jobs';

        if (! is_string($table) || ! $this->schema($configuration)->hasTable($table)) {
            throw new RuntimeException(sprintf('The [%s] queue table does not exist.', is_string($table) ? $table : 'jobs'));
        }
    }

    /**
     * Probe a Redis-backed queue.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function probeRedisQueue(array $configuration): void
    {
        $connection = $configuration['connection'] ?? 'default';

        if (! is_string($connection) || $connection === '') {
            $connection = 'default';
        }

        Redis::connection($connection)->ping();
    }

    /**
     * Get the queue connection configuration.
     *
     * @return array<string, mixed>
     */
    private function configuration(string $connection): array
    {
        $configuration = config("queue.connections.{$connection}");

        if (! is_array($configuration)) {
            throw new RuntimeException(sprintf('The [%s] queue connection is not configured.', $connection));
        }

        $configured = [];

        foreach ($configuration as $key => $value) {
            if (is_string($key)) {
                $configured[$key] = $value;
            }
        }

        return $configured;
    }

    /**
     * Get the schema builder for the queue connection.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function schema(array $configuration): Builder
    {
        $connection = $configuration['connection'] ?? null;

        $schema = is_string($connection) && $connection !== ''
            ? Schema::connection($connection)
            : Schema::getFacadeRoot();

        if (! $schema instanceof Builder) {
            throw new RuntimeException('The database schema builder is not available.');
        }

        return $schema;
    }
}
