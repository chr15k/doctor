<?php

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostics\ComposerLockIsFresh;

function doctor_composer_lock_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-composer-lock-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('checks Composer lock freshness through Composer check lock validation', function (): void {
    $basePath = doctor_composer_lock_base_path();
    file_put_contents($basePath.'/composer.json', '{}');
    file_put_contents($basePath.'/composer.lock', '{}');

    $this->app->setBasePath($basePath);

    Process::fake([
        '*' => Process::result(errorOutput: 'The lock file is not up to date.', exitCode: 1),
    ]);

    $result = (new ComposerLockIsFresh)->check();

    Process::assertRan(fn ($process): bool => $process->command === [
        'composer',
        'validate',
        '--check-lock',
        '--no-check-publish',
        '--no-check-all',
        '--no-interaction',
    ]);

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toBe('The lock file is not up to date.')
        ->and($result->remediation)->toBe('Refresh the lock file metadata without changing installed package versions.');
});

it('does not report unrelated Composer validation errors as stale lock files', function (): void {
    $basePath = doctor_composer_lock_base_path();
    file_put_contents($basePath.'/composer.json', '{}');
    file_put_contents($basePath.'/composer.lock', '{}');

    $this->app->setBasePath($basePath);

    Process::fake([
        '*' => Process::result(errorOutput: 'composer.json is invalid JSON.', exitCode: 2),
    ]);

    $result = (new ComposerLockIsFresh)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->code)->toBe('composer-lock-is-fresh.inspection-failed')
        ->and($result->summary)->toBe('Composer could not verify composer.lock freshness.')
        ->and($result->details)->toBe('composer.json is invalid JSON.');
});
