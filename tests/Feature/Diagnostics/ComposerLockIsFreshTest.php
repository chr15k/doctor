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
        '--no-ansi',
    ]);

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toBe('The lock file is not up to date.')
        ->and($result->remediation)->toBe('Run `composer update --lock` to refresh the lock file.');
});

it('reports constraint mismatches with the packages that need updating', function (): void {
    $basePath = doctor_composer_lock_base_path();
    file_put_contents($basePath.'/composer.json', '{}');
    file_put_contents($basePath.'/composer.lock', '{}');

    $this->app->setBasePath($basePath);

    Process::fake([
        '*' => Process::result(errorOutput: implode(PHP_EOL, [
            './composer.json is valid but your composer.lock has some errors',
            '# Lock file errors',
            '- The lock file is not up to date with the latest changes in composer.json, it is recommended that you run `composer update` or `composer update <package name>`.',
            '- Required package "laravel/passkeys" is in the lock file as "v0.2.1" but that does not satisfy your constraint "^1.27".',
            '- Required package "laravel/wayfinder" is not present in the lock file.',
            'This usually happens when composer files are incorrectly merged or the composer.json file is manually edited.',
            'Read more about correctly resolving merge conflicts https://getcomposer.org/doc/articles/resolving-merge-conflicts.md',
            'and prefer using the "require" command over editing the composer.json file directly https://getcomposer.org/doc/03-cli.md#require-r',
        ]), exitCode: 2),
    ]);

    $result = (new ComposerLockIsFresh)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->code)->toBe('composer-lock-is-fresh.constraint-mismatch')
        ->and($result->summary)->toBe('composer.lock does not satisfy the package constraints in composer.json.')
        ->and($result->remediation)->toBe('Run `composer update laravel/passkeys laravel/wayfinder` to lock versions that satisfy composer.json.')
        ->and($result->details)->toBe(implode(PHP_EOL, [
            '- The lock file is not up to date with the latest changes in composer.json.',
            '- Required package "laravel/passkeys" is in the lock file as "v0.2.1" but that does not satisfy your constraint "^1.27".',
            '- Required package "laravel/wayfinder" is not present in the lock file.',
        ]));
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
