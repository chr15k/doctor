<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Database\DatabaseManager;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\Details;
use Throwable;

class DatabaseConnectionIsAvailable extends Diagnostic
{
    public string $name = 'Database connects';

    public string $group = 'database';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-configured' => Outcome::skip('No database connections are configured.'),
            'manager-missing' => Outcome::skip('The database manager is not available.'),
            'unreachable' => Outcome::fail(
                summary: 'Laravel cannot connect to every configured database.',
                remediation: 'Check DB_CONNECTION and the database credentials in your environment file.',
            ),
            'reachable' => Outcome::pass('Laravel can connect to every configured database.'),
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

        if (! app()->bound('db')) {
            return $this->result('manager-missing');
        }

        /** @var DatabaseManager $database */
        $database = app('db');
        $failures = [];

        foreach ($connections as $connection) {
            try {
                $database->connection($connection)->getPdo();
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
     * Get configured database connection names.
     *
     * @return list<string>
     */
    private function connections(): array
    {
        $connections = config('database.connections');

        if (! is_array($connections)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($connections),
            static fn (mixed $connection): bool => is_string($connection) && $connection !== '',
        ));
    }
}
