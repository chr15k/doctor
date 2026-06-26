<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Database\Schema\Builder;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use RuntimeException;
use Throwable;

class SessionDriverIsReachable extends Diagnostic
{
    public string $name = 'Session driver is reachable';

    public string $group = 'session';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $driver = config('session.driver');

        if (! is_string($driver) || $driver === '') {
            return DiagnosticResult::skip('Laravel does not have a session driver configured.');
        }

        try {
            $this->probe($driver);
        } catch (Throwable $e) {
            return DiagnosticResult::fail('Laravel cannot reach the configured session driver.')
                ->withDetails($e->getMessage())
                ->suggest('Check SESSION_DRIVER and the backing session store configuration.');
        }

        return DiagnosticResult::pass('Laravel can reach the configured session driver.');
    }

    /**
     * Probe the session driver.
     */
    private function probe(string $driver): void
    {
        match ($driver) {
            'array', 'cookie' => null,
            'file' => $this->probeFileSessions(),
            'database' => $this->probeDatabaseSessions(),
            'redis' => $this->probeRedisSessions(),
            default => $this->probeSessionManager(),
        };
    }

    /**
     * Probe file-backed sessions.
     */
    private function probeFileSessions(): void
    {
        $path = config('session.files');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The session file path is not configured.');
        }

        if (! is_dir($path) || ! is_writable($path)) {
            throw new RuntimeException(sprintf('The session file path [%s] is not writable.', $path));
        }
    }

    /**
     * Probe database-backed sessions.
     */
    private function probeDatabaseSessions(): void
    {
        $table = config('session.table', 'sessions');

        if (! is_string($table) || ! $this->schema()->hasTable($table)) {
            throw new RuntimeException(sprintf('The [%s] session table does not exist.', is_string($table) ? $table : 'sessions'));
        }
    }

    /**
     * Probe Redis-backed sessions.
     */
    private function probeRedisSessions(): void
    {
        $connection = config('session.connection', 'default');

        if (! is_string($connection) || $connection === '') {
            $connection = 'default';
        }

        Redis::connection($connection)->ping();
    }

    /**
     * Get the schema builder for the session connection.
     */
    private function schema(): Builder
    {
        $connection = config('session.connection');

        $schema = is_string($connection) && $connection !== ''
            ? Schema::connection($connection)
            : Schema::getFacadeRoot();

        if (! $schema instanceof Builder) {
            throw new RuntimeException('The database schema builder is not available.');
        }

        return $schema;
    }

    /**
     * Resolve custom session drivers through Laravel's session manager.
     */
    private function probeSessionManager(): void
    {
        $manager = app('session');

        if (! $manager instanceof SessionManager) {
            throw new RuntimeException('The session manager is not available.');
        }

        $manager->driver();
    }
}
