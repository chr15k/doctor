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
        ->and($outcome->result->remediation)->toBe('Copy the example environment file to .env, then review its values.');
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

it('does not offer a fix when .env.example is missing', function (): void {
    $basePath = doctor_environment_base_path();

    $this->app->setBasePath($basePath);

    $offered = false;

    $report = $this->app->make(Doctor::class)
        ->runner(DiagnosticSelection::make(only: ['EnvironmentFileExists']))
        ->fixUsing(function (DiagnosticOutcome $outcome) use (&$offered): bool {
            $offered = true;

            return true;
        })
        ->run();

    $result = $report->diagnostics()[0]->result;

    expect($result->status->value)->toBe('fail')
        ->and($result->fixable)->toBeFalse()
        ->and($result->confirmation)->toBeNull()
        ->and($result->remediation)->toBe('Create an environment file or add .env.example as a template.')
        ->and($report->fixes())->toBe([])
        ->and($offered)->toBeFalse();
});
