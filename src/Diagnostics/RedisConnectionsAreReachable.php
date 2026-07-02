<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Redis;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\Details;
use Throwable;

class RedisConnectionsAreReachable extends Diagnostic
{
    public string $name = 'Redis connects';

    public string $group = 'cache';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-configured' => Outcome::skip('Laravel is not using Redis-backed cache, queue, or session storage.'),
            'unreachable' => Outcome::fail(
                summary: 'Laravel cannot reach every active Redis connection.',
                remediation: 'Check Redis host, port, credentials, and client configuration.',
            ),
            'reachable' => Outcome::pass('Laravel can reach every active Redis connection.'),
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
                ->withDetails(Details::failures($failures));
        }

        return $this->result('reachable');
    }

    /**
     * Get Redis connection names used by selected Laravel services.
     *
     * @return list<string>
     */
    private function connections(): array
    {
        return array_values(array_unique(array_filter([
            $this->cacheConnection(),
            $this->queueConnection(),
            $this->sessionConnection(),
        ], is_string(...))));
    }

    /**
     * Get the Redis connection used by the default cache store.
     */
    private function cacheConnection(): ?string
    {
        $store = config()->string('cache.default', '');

        if ($store === '') {
            return null;
        }

        $configuration = config("cache.stores.{$store}");

        if (! is_array($configuration) || ($configuration['driver'] ?? null) !== 'redis') {
            return null;
        }

        return $this->configuredConnection($configuration['connection'] ?? null);
    }

    /**
     * Get the Redis connection used by the default queue connection.
     */
    private function queueConnection(): ?string
    {
        $connection = config()->string('queue.default', '');

        if ($connection === '') {
            return null;
        }

        $configuration = config("queue.connections.{$connection}");

        if (! is_array($configuration) || ($configuration['driver'] ?? null) !== 'redis') {
            return null;
        }

        return $this->configuredConnection($configuration['connection'] ?? null);
    }

    /**
     * Get the Redis connection used by the configured session driver.
     */
    private function sessionConnection(): ?string
    {
        if (config('session.driver') !== 'redis') {
            return null;
        }

        return $this->configuredConnection(config('session.connection'));
    }

    /**
     * Normalize a Redis connection name from configuration.
     */
    private function configuredConnection(mixed $connection): string
    {
        return is_string($connection) && $connection !== '' ? $connection : 'default';
    }
}
