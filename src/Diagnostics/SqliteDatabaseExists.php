<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\File;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Outcome;

class SqliteDatabaseExists extends Diagnostic implements Fixable
{
    public string $name = 'SQLite database exists';

    public string $group = 'database';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-sqlite' => Outcome::skip('The default database connection is not SQLite.'),
            'not-file' => Outcome::skip('The SQLite connection does not use a database file.'),
            'exists' => Outcome::pass('The SQLite database file exists.'),
            'missing' => Outcome::fail(
                summary: 'The SQLite database file does not exist.',
                remediation: 'Create the SQLite database file at the configured path.',
                confirmation: 'Would you like Doctor to create the SQLite database file?',
            ),
            'already-exists' => Outcome::skip('The SQLite database file already exists.'),
            'creation-failed' => Outcome::fail('The SQLite database file could not be created.'),
            'created' => Outcome::pass('The SQLite database file was created.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (config('database.default') !== 'sqlite') {
            return $this->result('not-sqlite');
        }

        $database = config()->string('database.connections.sqlite.database', '');

        if ($database === '' || $database === ':memory:') {
            return $this->result('not-file');
        }

        if (! str_starts_with($database, DIRECTORY_SEPARATOR)) {
            $database = base_path($database);
        }

        if (is_file($database)) {
            return $this->result('exists')
                ->withContext('database', $database);
        }

        return $this->result('missing')
            ->withContext('database', $database);
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        /** @var string $database */
        $database = $result->context['database'];

        if (is_file($database)) {
            return $this->fixResult('already-exists');
        }

        File::ensureDirectoryExists(dirname($database));

        if (File::put($database, '') === false) {
            return $this->fixResult('creation-failed');
        }

        return $this->fixResult('created');
    }
}
