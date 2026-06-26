<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Redis;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Throwable;

class RedisConnectionsAreReachable extends Diagnostic
{
    public string $name = 'Redis connections are reachable';

    public string $group = 'cache';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-configured' => Outcome::skip('Laravel does not have Redis connections configured.'),
            'unreachable' => Outcome::fail(
                summary: 'Laravel cannot reach every Redis connection.',
                remediation: 'Check Redis host, port, credentials, and client configuration.',
            ),
            'reachable' => Outcome::pass('Laravel can reach every Redis connection.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $connections = $this->connections();

        if ($connections === []) {
            return $this->result('not-configured');
        }

        $failures = [];

        foreach ($connections as $connection) {
            try {
                Redis::connection($connection)->ping();
            } catch (Throwable $e) {
                $failures[$connection] = $e->getMessage();
            }
        }

        if ($failures !== []) {
            return $this->result('unreachable')
                ->withDetails($this->formatFailures($failures));
        }

        return $this->result('reachable');
    }

    /**
     * Get configured Redis connection names.
     *
     * @return list<string>
     */
    private function connections(): array
    {
        $redis = config('database.redis');

        if (! is_array($redis)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($redis),
            static fn (mixed $connection): bool => is_string($connection)
                && ! in_array($connection, ['client', 'options', 'clusters'], true),
        ));
    }

    /**
     * Format Redis failures.
     *
     * @param  array<string, string>  $failures
     */
    private function formatFailures(array $failures): string
    {
        return implode(PHP_EOL, array_map(
            static fn (string $connection, string $message): string => sprintf('- %s: %s', $connection, $message),
            array_keys($failures),
            $failures,
        ));
    }
}
