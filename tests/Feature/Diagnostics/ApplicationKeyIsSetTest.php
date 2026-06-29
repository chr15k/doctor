<?php

use Laravel\Doctor\Diagnostics\ApplicationKeyIsSet;
use Laravel\Doctor\DiagnosticSelection;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticResult;

function doctor_application_key_environment_path(string $contents): string
{
    $environmentPath = sys_get_temp_dir().'/laravel-doctor-application-key-'.str_replace('.', '', uniqid('', true));

    mkdir($environmentPath, 0775, true);
    file_put_contents($environmentPath.'/.env', $contents);

    return $environmentPath;
}

it('passes when the application key is set', function (): void {
    config(['app.key' => 'base64:'.str_repeat('a', 44)]);

    $report = $this->app->make(Doctor::class)
        ->run(DiagnosticSelection::make(only: ['ApplicationKeyIsSet']));

    $outcome = $report->diagnostics()[0];

    expect($outcome->result->status->value)->toBe('pass')
        ->and($outcome->result->summary)->toBe('Laravel has an application key.');
});

it('reports a missing application key', function (): void {
    config(['app.key' => '']);

    $report = $this->app->make(Doctor::class)
        ->run(DiagnosticSelection::make(only: ['ApplicationKeyIsSet']));

    $outcome = $report->diagnostics()[0];

    expect($outcome->result->status->value)->toBe('fail')
        ->and($outcome->result->code)->toBe('application-key-is-set.missing')
        ->and($outcome->result->summary)->toBe('Laravel does not have an application key.')
        ->and($outcome->result->confirmation)->toBe('Would you like Doctor to generate an application key using `artisan key:generate`?')
        ->and($outcome->result->remediation)->toBe('Generate an application key with `php artisan key:generate`.');
});

it('generates an application key when fixed', function (): void {
    config(['app.key' => '']);

    $environmentPath = doctor_application_key_environment_path("APP_KEY=\n");

    $this->app->useEnvironmentPath($environmentPath);

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        new ApplicationKeyIsSet,
        DiagnosticResult::fail('Laravel does not have an application key.'),
    ));

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->code)->toBe('application-key-is-set.fix.generated')
        ->and($fix->result->summary)->toBe('The application key was generated.')
        ->and(file_get_contents($environmentPath.'/.env'))->toContain('APP_KEY=base64:')
        ->and(str_starts_with((string) config('app.key'), 'base64:'))->toBeTrue();
});

it('adds an application key to the environment file when the variable is missing', function (): void {
    config(['app.key' => '']);

    $environmentPath = doctor_application_key_environment_path("APP_NAME=Laravel\n");

    $this->app->useEnvironmentPath($environmentPath);

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        new ApplicationKeyIsSet,
        DiagnosticResult::fail('Laravel does not have an application key.'),
    ));

    expect($fix->result->status->value)->toBe('pass')
        ->and(file_get_contents($environmentPath.'/.env'))->toContain("APP_NAME=Laravel\nAPP_KEY=base64:")
        ->and(str_starts_with((string) config('app.key'), 'base64:'))->toBeTrue();
});

it('reports when the application key cannot be written to the environment file', function (): void {
    $environmentPath = sys_get_temp_dir().'/laravel-doctor-application-key-missing-env-'.str_replace('.', '', uniqid('', true));

    mkdir($environmentPath, 0775, true);

    $this->app->useEnvironmentPath($environmentPath);

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        new ApplicationKeyIsSet,
        DiagnosticResult::fail('Laravel does not have an application key.'),
    ));

    expect($fix->result->status->value)->toBe('fail')
        ->and($fix->result->code)->toBe('application-key-is-set.fix.generation-failed')
        ->and($fix->result->summary)->toBe('The application key could not be generated.')
        ->and($fix->result->details)->toContain('could not be updated');
});
