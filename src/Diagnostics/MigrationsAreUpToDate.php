<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\EnvironmentMode;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Details;
use Throwable;

class MigrationsAreUpToDate extends Diagnostic implements Fixable
{
    public string $name = 'Migrations are current';

    public string $group = 'database';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'no-files' => 'The application does not have migration files.',
            'repository-missing' => Message::make(
                summary: 'The migrations table does not exist.',
                remediation: 'Run `php artisan migrate` to create the migrations table and apply the migrations.',
                confirmation: 'Create the migrations table and apply pending migrations using `php artisan migrate`?',
            )->link(Link::docs('migrations', 'running-migrations')),
            'inspection-failed' => 'The application could not inspect database migrations.',
            'current' => 'Database migrations are current.',
            'pending' => Message::make(
                summary: 'Database migrations are pending.',
                remediation: 'Run `php artisan migrate` to apply the pending migrations.',
                confirmation: 'Apply pending migrations using `php artisan migrate`?',
            )->link(Link::docs('migrations', 'running-migrations')),
            'migration-failed' => 'Database migrations could not be applied.',
            'migrated' => 'Database migrations were applied.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        try {
            $files = $migrator->getMigrationFiles(array_values(array_unique([
                ...$migrator->paths(),
                base_path('database/migrations'),
            ])));

            if ($files === []) {
                return $this->pass('no-files');
            }

            if (! $migrator->repositoryExists()) {
                return $this->fail('repository-missing')->fixable(EnvironmentMode::Local);
            }

            $pending = array_values(array_diff(
                array_keys($files),
                $migrator->getRepository()->getRan(),
            ));
        } catch (Throwable $e) {
            return $this->fail('inspection-failed')
                ->withDetails($e->getMessage());
        }

        if ($pending === []) {
            return $this->pass('current');
        }

        return $this->fail('pending')
            ->fixable(EnvironmentMode::Local)
            ->withDetails(Details::bullets($pending));
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $process = Process::path(base_path())->run([
            PHP_BINARY,
            'artisan',
            'migrate',
            '--no-interaction',
        ]);

        if (! $process->successful()) {
            return $this->fixFailed('migration-failed')
                ->withDetails(Details::processOutput(
                    $process->output(),
                    $process->errorOutput(),
                    'The migrate command exited without output.',
                ));
        }

        return $this->fixed('migrated');
    }
}
