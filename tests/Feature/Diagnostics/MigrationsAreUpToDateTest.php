<?php

use Laravel\Doctor\Diagnostics\MigrationsAreUpToDate;

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
        ->and($result->remediation[0])->toBe('Run pending database migrations.');
});
