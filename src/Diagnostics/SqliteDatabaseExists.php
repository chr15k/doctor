<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\File;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

class SqliteDatabaseExists extends Diagnostic implements Fixable
{
    public string $name = 'SQLite database exists';

    public string $group = 'database';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (config('database.default') !== 'sqlite') {
            return DiagnosticResult::skip('The default database connection is not SQLite.');
        }

        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            return DiagnosticResult::skip('The SQLite connection does not use a database file.');
        }

        if (! str_starts_with($database, DIRECTORY_SEPARATOR)) {
            $database = base_path($database);
        }

        if (is_file($database)) {
            return DiagnosticResult::pass('The SQLite database file exists.')
                ->withContext('database', $database);
        }

        return DiagnosticResult::fail('The SQLite database file does not exist.')
            ->withContext('database', $database)
            ->confirmUsing('Would you like Doctor to create the SQLite database file?')
            ->suggest('Create the SQLite database file at the configured path.');
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $database = $result->context['database'] ?? null;

        if (! is_string($database) || $database === '') {
            return FixResult::fail('The SQLite database file path was not available from the diagnostic result.');
        }

        if (is_file($database)) {
            return FixResult::skip('The SQLite database file already exists.');
        }

        File::ensureDirectoryExists(dirname($database));

        if (File::put($database, '') === false) {
            return FixResult::fail('The SQLite database file could not be created.');
        }

        return FixResult::pass('The SQLite database file was created.');
    }
}
