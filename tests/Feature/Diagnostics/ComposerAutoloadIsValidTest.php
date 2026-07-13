<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostics\ComposerAutoloadIsValid;
use Laravel\Doctor\Results\DiagnosticResult;

function doctor_composer_autoload_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-composer-autoload-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('checks Composer autoload validity with a strict dry run', function (): void {
    $basePath = doctor_composer_autoload_base_path();
    mkdir($basePath.'/vendor');
    file_put_contents($basePath.'/composer.json', '{}');
    file_put_contents($basePath.'/vendor/autoload.php', '<?php');

    $this->app->setBasePath($basePath);

    Process::fake([
        '*' => Process::result(
            output: 'Class App\\Foo located in ./app/Bar.php does not comply with psr-4 autoloading standard.',
            exitCode: 1,
        ),
    ]);

    $result = (new ComposerAutoloadIsValid)->check();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'composer',
        'dump-autoload',
        '--dry-run',
        '--optimize',
        '--strict-psr',
        '--strict-ambiguous',
        '--no-interaction',
    ] && $process->path === $basePath);

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('does not comply with psr-4')
        ->and($result->confirmation)->toBe('Regenerate Composer autoload files using `composer dump-autoload`?')
        ->and($result->remediation)->toBe('Regenerate Composer autoload files with `composer dump-autoload`.');
});

it('regenerates Composer autoload files when fixed', function (): void {
    $basePath = doctor_composer_autoload_base_path();
    file_put_contents($basePath.'/composer.json', '{}');

    $this->app->setBasePath($basePath);

    Process::fake([
        '*' => Process::result(output: 'Generated autoload files'),
    ]);

    $fix = (new ComposerAutoloadIsValid)->fix(DiagnosticResult::fail('autoload'));

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'composer',
        'dump-autoload',
        '--optimize',
        '--strict-psr',
        '--strict-ambiguous',
        '--no-interaction',
    ] && $process->path === $basePath);

    expect($fix->status->value)->toBe('pass');
});
