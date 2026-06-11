<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Database\Migrations\Migrator;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Throwable;

class MigrationsAreUpToDate extends Diagnostic
{
    public string $name = 'Migrations are up to date';

    public string $group = 'database';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! app()->bound('migrator')) {
            return DiagnosticResult::skip('The migration service is not available.');
        }

        /** @var Migrator $migrator */
        $migrator = app('migrator');

        try {
            $files = $migrator->getMigrationFiles(array_values(array_unique([
                ...$migrator->paths(),
                base_path('database/migrations'),
            ])));

            if ($files === []) {
                return DiagnosticResult::pass('The application does not have migration files.');
            }

            if (! $migrator->repositoryExists()) {
                return DiagnosticResult::fail('The migrations table does not exist.')
                    ->suggest('Create the migrations table and run pending migrations.');
            }

            $pending = array_values(array_diff(
                array_keys($files),
                $migrator->getRepository()->getRan(),
            ));
        } catch (Throwable $e) {
            return DiagnosticResult::fail('Laravel could not inspect database migrations.')
                ->withDetails($e->getMessage());
        }

        if ($pending === []) {
            return DiagnosticResult::pass('Database migrations are current.');
        }

        return DiagnosticResult::fail('Database migrations are pending.')
            ->withDetails(implode(PHP_EOL, array_map(
                static fn (string $migration): string => '- '.$migration,
                $pending,
            )))
            ->suggest('Run pending database migrations.');
    }
}
