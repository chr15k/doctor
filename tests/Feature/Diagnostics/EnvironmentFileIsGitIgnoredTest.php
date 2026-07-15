<?php

use Laravel\Doctor\Diagnostics\EnvironmentFileIsGitIgnored;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\Results\DiagnosticOutcome;

function doctor_environment_ignored_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-environment-ignored-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('passes when environment files are gitignored', function (): void {
    $basePath = doctor_environment_ignored_base_path();

    file_put_contents($basePath.'/.gitignore', ".env*\n");

    $this->app->setBasePath($basePath);

    $result = (new EnvironmentFileIsGitIgnored)->check();

    expect($result->status->value)->toBe('pass');
});

it('adds .env to an existing .gitignore when fixed', function (): void {
    $basePath = doctor_environment_ignored_base_path();

    file_put_contents($basePath.'/.gitignore', "/vendor\n");

    $this->app->setBasePath($basePath);

    $diagnostic = new EnvironmentFileIsGitIgnored;
    $result = $diagnostic->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->fixable)->toBeTrue()
        ->and($result->confirmation)->toBe('Add .env to .gitignore?');

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome($diagnostic, $result));

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->summary)->toBe('.env was added to .gitignore.')
        ->and(file_get_contents($basePath.'/.gitignore'))->toBe("/vendor\n.env\n")
        ->and($diagnostic->check()->status->value)->toBe('pass');
});

it('creates a .gitignore that ignores .env when fixed', function (): void {
    $basePath = doctor_environment_ignored_base_path();

    $this->app->setBasePath($basePath);

    $diagnostic = new EnvironmentFileIsGitIgnored;
    $result = $diagnostic->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->fixable)->toBeTrue()
        ->and($result->confirmation)->toBe('Create a .gitignore file that ignores .env?');

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome($diagnostic, $result));

    expect($fix->result->status->value)->toBe('pass')
        ->and(file_get_contents($basePath.'/.gitignore'))->toBe(".env\n")
        ->and($diagnostic->check()->status->value)->toBe('pass');
});
