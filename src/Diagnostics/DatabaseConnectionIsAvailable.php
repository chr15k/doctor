<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Database\DatabaseManager;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Throwable;

class DatabaseConnectionIsAvailable extends Diagnostic
{
    public string $name = 'Database connects';

    public string $group = 'database';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $connection = config('database.default');

        if (! is_string($connection) || $connection === '') {
            return DiagnosticResult::skip('No default database connection is configured.');
        }

        if (! app()->bound('db')) {
            return DiagnosticResult::skip('The database manager is not available.');
        }

        /** @var DatabaseManager $database */
        $database = app('db');

        try {
            $database->connection($connection)->getPdo();
        } catch (Throwable $e) {
            return DiagnosticResult::fail('Laravel cannot connect to the default database.')
                ->withDetails($e->getMessage())
                ->suggest('Check DB_CONNECTION and the database credentials in your environment file.');
        }

        return DiagnosticResult::pass('Laravel can connect to the default database.');
    }
}
