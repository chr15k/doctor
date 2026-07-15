<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostics\MigrationsAreUpToDate;
use Laravel\Doctor\EnvironmentMode;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;

it('reports pending migrations', function (): void {
    $this->app->instance('migrator', new class
    {
        public function getMigrationFiles(array $paths): array
        {
            return ['2024_01_01_000000_create_users_table' => $paths[0].'/2024_01_01_000000_create_users_table.php'];
        }

        public function repositoryExists(): bool
        {
            return true;
        }

        public function getRepository(): object
        {
            return new class
            {
                public function getRan(): array
                {
                    return [];
                }
            };
        }

        public function paths(): array
        {
            return [];
        }
    });

    $result = (new MigrationsAreUpToDate)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('2024_01_01_000000_create_users_table')
        ->and($result->confirmation)->toBe('Apply pending migrations using `php artisan migrate`?')
        ->and($result->remediation)->toBe('Run `php artisan migrate` to apply the pending migrations.')
        ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local])
        ->and($result->fixableIn(EnvironmentMode::Local))->toBeTrue()
        ->and($result->fixableIn(EnvironmentMode::Production))->toBeFalse()
        ->and($result->links)->toEqual([Link::docs('migrations', 'running-migrations')]);
});

it('offers to create the migration repository locally', function (): void {
    $this->app->instance('migrator', new class
    {
        public function getMigrationFiles(array $paths): array
        {
            return ['2024_01_01_000000_create_users_table' => $paths[0].'/2024_01_01_000000_create_users_table.php'];
        }

        public function repositoryExists(): bool
        {
            return false;
        }

        public function paths(): array
        {
            return [];
        }
    });

    $result = (new MigrationsAreUpToDate)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->confirmation)->toBe('Create the migrations table and apply pending migrations using `php artisan migrate`?')
        ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local]);
});

it('applies pending migrations when fixed', function (): void {
    Process::fake([
        '*' => Process::result(output: 'Migrating: 2024_01_01_000000_create_users_table'),
    ]);

    $fix = (new MigrationsAreUpToDate)->fix(DiagnosticResult::fail('pending'));

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        PHP_BINARY,
        'artisan',
        'migrate',
        '--no-interaction',
    ] && $process->path === base_path());

    expect($fix->status->value)->toBe('pass')
        ->and($fix->code)->toBe('migrations-are-up-to-date.fix.migrated')
        ->and($fix->summary)->toBe('Database migrations were applied.');
});

it('reports when pending migrations cannot be applied', function (): void {
    Process::fake([
        '*' => Process::result(errorOutput: 'Database connection refused.', exitCode: 1),
    ]);

    $fix = (new MigrationsAreUpToDate)->fix(DiagnosticResult::fail('pending'));

    expect($fix->status->value)->toBe('fail')
        ->and($fix->code)->toBe('migrations-are-up-to-date.fix.migration-failed')
        ->and($fix->summary)->toBe('Database migrations could not be applied.')
        ->and($fix->details)->toContain('Database connection refused.');
});
