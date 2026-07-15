<?php

use Laravel\Doctor\Diagnostics\DebugModeMatchesEnvironment;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\Results\DiagnosticOutcome;

function doctor_debug_environment_path(string $contents): string
{
    $environmentPath = sys_get_temp_dir().'/laravel-doctor-debug-'.str_replace('.', '', uniqid('', true));

    mkdir($environmentPath, 0775, true);
    file_put_contents($environmentPath.'/.env', $contents);

    return $environmentPath;
}

it('reports debug mode in production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    config(['app.debug' => true]);

    $result = (new DebugModeMatchesEnvironment)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->summary)->toBe('Debug mode is enabled in production.')
        ->and($result->fixable)->toBeTrue()
        ->and($result->confirmation)->toBe('Set APP_DEBUG=false in the application environment file?');
});

it('treats unmapped environments as production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'preview');
    config(['app.debug' => true]);

    $result = (new DebugModeMatchesEnvironment)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->summary)->toBe('Debug mode is enabled in production.');
});

it('honors custom local environment mappings', function (): void {
    $this->app->detectEnvironment(fn (): string => 'dev');
    config([
        'app.debug' => true,
        'doctor.environments.local' => ['local', 'dev'],
    ]);

    $result = (new DebugModeMatchesEnvironment)->check();

    expect($result->status->value)->toBe('pass')
        ->and($result->summary)->toBe('Debug mode matches the application environment.');
});

it('disables debug mode in the application environment file', function (): void {
    $environmentPath = doctor_debug_environment_path("APP_NAME=Laravel\nAPP_DEBUG=true\n");

    $this->app->useEnvironmentPath($environmentPath);
    $this->app->detectEnvironment(fn (): string => 'production');
    config(['app.debug' => true]);

    $diagnostic = new DebugModeMatchesEnvironment;

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        $diagnostic,
        $diagnostic->check(),
    ));

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->summary)->toBe('Debug mode was disabled in the application environment file.')
        ->and(file_get_contents($environmentPath.'/.env'))->toContain('APP_DEBUG=false')
        ->and(config('app.debug'))->toBeFalse();
});

it('adds APP_DEBUG when it is missing from the application environment file', function (): void {
    $environmentPath = doctor_debug_environment_path("APP_NAME=Laravel\n");

    $this->app->useEnvironmentPath($environmentPath);
    $this->app->detectEnvironment(fn (): string => 'production');
    config(['app.debug' => true]);

    $diagnostic = new DebugModeMatchesEnvironment;

    $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        $diagnostic,
        $diagnostic->check(),
    ));

    expect(file_get_contents($environmentPath.'/.env'))->toContain('APP_DEBUG=false');
});
