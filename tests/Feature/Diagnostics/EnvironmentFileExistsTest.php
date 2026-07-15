<?php

use Laravel\Doctor\Diagnostics\EnvironmentFileExists;
use Laravel\Doctor\DiagnosticSelection;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\Results\DiagnosticOutcome;

function doctor_environment_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-env-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('passes when .env exists', function (): void {
    $basePath = doctor_environment_base_path();
    touch($basePath.'/.env');

    $this->app->setBasePath($basePath);

    $report = $this->app->make(Doctor::class)
        ->run(DiagnosticSelection::make(only: ['EnvironmentFileExists']));

    $outcome = $report->diagnostics()[0];

    expect($outcome->result->status->value)->toBe('pass')
        ->and($outcome->result->summary)->toBe('The application has an environment file.');
});

it('passes when an environment-specific file exists', function (): void {
    $basePath = doctor_environment_base_path();
    touch($basePath.'/.env.testing');

    $this->app->setBasePath($basePath);

    $report = $this->app->make(Doctor::class)
        ->run(DiagnosticSelection::make(only: ['EnvironmentFileExists']));

    expect($report->diagnostics()[0]->result->status->value)->toBe('pass');
});

it('reports a missing environment file with guidance when an example exists', function (): void {
    $basePath = doctor_environment_base_path();
    file_put_contents($basePath.'/.env.example', "APP_NAME=Laravel\n");

    $this->app->setBasePath($basePath);

    $report = $this->app->make(Doctor::class)
        ->run(DiagnosticSelection::make(only: ['EnvironmentFileExists']));

    $outcome = $report->diagnostics()[0];

    expect($outcome->result->status->value)->toBe('fail')
        ->and($outcome->result->summary)->toBe('The application does not have an environment file.')
        ->and($outcome->result->confirmation)->toBe('Copy .env.example to .env?')
        ->and($outcome->result->remediation)->toBe('Run `cp .env.example .env`, then review the copied values.');
});

it('copies .env.example to .env when fixed', function (): void {
    $basePath = doctor_environment_base_path();
    file_put_contents($basePath.'/.env.example', "APP_NAME=Laravel\n");

    $this->app->setBasePath($basePath);

    $diagnostic = new EnvironmentFileExists;

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        $diagnostic,
        $diagnostic->check(),
    ));

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->summary)->toBe('The .env file was created from .env.example.')
        ->and(file_get_contents($basePath.'/.env'))->toBe("APP_NAME=Laravel\n");
});

it('does not overwrite an existing .env when fixed', function (): void {
    $basePath = doctor_environment_base_path();
    file_put_contents($basePath.'/.env.example', "APP_NAME=Example\n");

    $this->app->setBasePath($basePath);

    $diagnostic = new EnvironmentFileExists;
    $result = $diagnostic->check();

    file_put_contents($basePath.'/.env', "APP_NAME=Existing\n");

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        $diagnostic,
        $result,
    ));

    expect($fix->result->status->value)->toBe('pass')
        ->and(file_get_contents($basePath.'/.env'))->toBe("APP_NAME=Existing\n");
});

it('creates an empty .env when .env.example is missing', function (): void {
    $basePath = doctor_environment_base_path();

    $this->app->setBasePath($basePath);

    $diagnostic = new EnvironmentFileExists;
    $result = $diagnostic->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->fixable)->toBeTrue()
        ->and($result->confirmation)->toBe('Create an empty .env file?')
        ->and($result->remediation)->toBe("Create a .env file with the application's environment variables.");

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        $diagnostic,
        $result,
    ));

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->summary)->toBe('An empty .env file was created.')
        ->and(file_get_contents($basePath.'/.env'))->toBe('');
});
