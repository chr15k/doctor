<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostics\ComposerLockIsFresh;
use Laravel\Doctor\EnvironmentMode;
use Laravel\Doctor\Results\DiagnosticResult;

function doctor_composer_lock_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-composer-lock-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('offers to generate a missing lock file locally', function (): void {
    $basePath = doctor_composer_lock_base_path();
    file_put_contents($basePath.'/composer.json', '{}');

    $this->app->setBasePath($basePath);

    $result = (new ComposerLockIsFresh)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->code)->toBe('composer-lock-is-fresh.lock-missing')
        ->and($result->confirmation)->toBe('Generate composer.lock and install dependencies using `composer install`?')
        ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local])
        ->and($result->fixableIn(EnvironmentMode::Production))->toBeFalse();
});

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
        ->and($result->remediation)->toBe('Run `composer update --lock` to refresh the lock file.')
        ->and($result->confirmation)->toBe('Refresh composer.lock using `composer update --lock`?')
        ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local]);
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
        ->and($result->confirmation)->toBe('Update laravel/passkeys laravel/wayfinder using `composer update laravel/passkeys laravel/wayfinder`?')
        ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local])
        ->and($result->context['packages'])->toBe(['laravel/passkeys', 'laravel/wayfinder'])
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
        ->and($result->fixable)->toBeFalse()
        ->and($result->details)->toBe('composer.json is invalid JSON.');
});

it('runs the Composer repair for each fixable outcome', function (Closure $result, array $command): void {
    $basePath = doctor_composer_lock_base_path();
    file_put_contents($basePath.'/composer.json', '{}');

    $this->app->setBasePath($basePath);

    Process::fake([
        '*' => Process::result(output: 'Writing lock file'),
    ]);

    $fix = (new ComposerLockIsFresh)->fix($result());

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === $command
        && $process->path === $basePath);

    expect($fix->status->value)->toBe('pass')
        ->and($fix->code)->toBe('composer-lock-is-fresh.fix.fixed')
        ->and($fix->summary)->toBe('composer.lock was repaired.');
})->with([
    'missing lock file' => [
        fn (): DiagnosticResult => DiagnosticResult::fail('missing', 'composer-lock-is-fresh.lock-missing'),
        ['composer', 'install', '--no-interaction'],
    ],
    'stale lock file' => [
        fn (): DiagnosticResult => DiagnosticResult::fail('stale', 'composer-lock-is-fresh.stale'),
        ['composer', 'update', '--lock', '--no-interaction'],
    ],
    'constraint mismatch' => [
        fn (): DiagnosticResult => DiagnosticResult::fail('mismatch', 'composer-lock-is-fresh.constraint-mismatch')
            ->withContext('packages', ['laravel/passkeys', 'laravel/wayfinder']),
        ['composer', 'update', 'laravel/passkeys', 'laravel/wayfinder', '--no-interaction'],
    ],
]);

it('reports when Composer cannot repair the lock file', function (): void {
    Process::fake([
        '*' => Process::result(errorOutput: 'Dependency resolution failed.', exitCode: 2),
    ]);

    $fix = (new ComposerLockIsFresh)->fix(
        DiagnosticResult::fail('stale', 'composer-lock-is-fresh.stale'),
    );

    expect($fix->status->value)->toBe('fail')
        ->and($fix->code)->toBe('composer-lock-is-fresh.fix.fix-failed')
        ->and($fix->summary)->toBe('composer.lock could not be repaired.')
        ->and($fix->details)->toContain('Dependency resolution failed.');
});
