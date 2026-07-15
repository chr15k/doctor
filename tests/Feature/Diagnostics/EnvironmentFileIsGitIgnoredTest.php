<?php

use Laravel\Doctor\Diagnostics\EnvironmentFileIsGitIgnored;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\EnvironmentMode;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\Link;

function doctor_environment_ignored_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-environment-ignored-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

beforeEach(function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');
});

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
        ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local])
        ->and($result->confirmation)->toBe('Add .env to .gitignore?')
        ->and($result->links)->toEqual([Link::docs('configuration', 'environment-configuration')]);

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
        ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local])
        ->and($result->confirmation)->toBe('Create a .gitignore file that ignores .env?')
        ->and($result->links)->toEqual([Link::docs('configuration', 'environment-configuration')]);

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome($diagnostic, $result));

    expect($fix->result->status->value)->toBe('pass')
        ->and(file_get_contents($basePath.'/.gitignore'))->toBe(".env\n")
        ->and($diagnostic->check()->status->value)->toBe('pass');
});

it('does not offer to update .gitignore in production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    $basePath = doctor_environment_ignored_base_path();
    file_put_contents($basePath.'/.gitignore', "/vendor\n");

    $this->app->setBasePath($basePath);

    $outcome = $this->app->make(Doctor::class)
        ->only('EnvironmentFileIsGitIgnored')->run()
        ->diagnostics()[0];

    expect($outcome->result->status->value)->toBe('fail')
        ->and($outcome->fixable())->toBeFalse()
        ->and($outcome->result->remediation)->toBe('Add .env to .gitignore so secrets are not committed.');
});
