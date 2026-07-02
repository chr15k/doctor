<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Database\Schema\Builder;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use RuntimeException;
use Throwable;

class SessionDriverIsReachable extends Diagnostic
{
    public string $name = 'Session connects';

    public string $group = 'session';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-configured' => Outcome::skip('Laravel does not have a session driver configured.'),
            'unreachable' => Outcome::fail(
                summary: 'Laravel cannot reach the configured session driver.',
                remediation: 'Check SESSION_DRIVER and the backing session store configuration.',
            ),
            'reachable' => Outcome::pass('Laravel can reach the configured session driver.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $driver = config()->string('session.driver', '');

        if ($driver === '') {
            return $this->result('not-configured');
        }

        try {
            $this->probe($driver);
        } catch (Throwable $e) {
            return $this->result('unreachable')
                ->withDetails($e->getMessage());
        }

        return $this->result('reachable');
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
        $path = config()->string('session.files', '');

        if ($path === '') {
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
        $table = config()->string('session.table', 'sessions');

        if (! $this->schema()->hasTable($table)) {
            throw new RuntimeException(sprintf('The [%s] session table does not exist.', $table));
        }
    }

    /**
     * Probe Redis-backed sessions.
     */
    private function probeRedisSessions(): void
    {
        $connection = config('session.connection');

        Redis::connection(is_string($connection) && $connection !== '' ? $connection : 'default')->ping();
    }

    /**
     * Get the schema builder for the session connection.
     */
    private function schema(): Builder
    {
        $connection = config('session.connection');

        return Schema::connection(is_string($connection) && $connection !== '' ? $connection : null);
    }

    /**
     * Resolve custom session drivers through Laravel's session manager.
     */
    private function probeSessionManager(): void
    {
        app(SessionManager::class)->driver();
    }
}
