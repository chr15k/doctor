<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Database\Migrations\Migrator;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Throwable;

class MigrationsAreUpToDate extends Diagnostic
{
    public string $name = 'Migrations are up to date';

    public string $group = 'database';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'service-missing' => Outcome::skip('The migration service is not available.'),
            'no-files' => Outcome::pass('The application does not have migration files.'),
            'repository-missing' => Outcome::fail(
                summary: 'The migrations table does not exist.',
                remediation: 'Create the migrations table and run pending migrations.',
            ),
            'inspection-failed' => Outcome::fail('Laravel could not inspect database migrations.'),
            'current' => Outcome::pass('Database migrations are current.'),
            'pending' => Outcome::fail(
                summary: 'Database migrations are pending.',
                remediation: 'Run pending database migrations.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! app()->bound('migrator')) {
            return $this->result('service-missing');
        }

        /** @var Migrator $migrator */
        $migrator = app('migrator');

        try {
            $files = $migrator->getMigrationFiles(array_values(array_unique([
                ...$migrator->paths(),
                base_path('database/migrations'),
            ])));

            if ($files === []) {
                return $this->result('no-files');
            }

            if (! $migrator->repositoryExists()) {
                return $this->result('repository-missing');
            }

            $pending = array_values(array_diff(
                array_keys($files),
                $migrator->getRepository()->getRan(),
            ));
        } catch (Throwable $e) {
            return $this->result('inspection-failed')
                ->withDetails($e->getMessage());
        }

        if ($pending === []) {
            return $this->result('current');
        }

        return $this->result('pending')
            ->withDetails(implode(PHP_EOL, array_map(
                static fn (string $migration): string => '- '.$migration,
                $pending,
            )));
    }
}
