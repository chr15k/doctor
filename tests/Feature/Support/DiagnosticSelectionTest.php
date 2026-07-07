<?php

use Laravel\Doctor\Diagnostics\ApplicationKeyIsSet;
use Laravel\Doctor\Diagnostics\EnvironmentFileIsGitIgnored;
use Laravel\Doctor\DiagnosticSelection;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\DatabaseDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\LaravelPackageDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PackagedDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PassingDiagnostic;

it('filters diagnostics by group', function (): void {
    $doctor = (new Doctor($this->app))->diagnostics([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
    ]);

    $report = $doctor->run(DiagnosticSelection::make(only: ['database']));

    expect($report->diagnostics())->toHaveCount(1)
        ->and($report->diagnostics()[0]->diagnostic::class)->toBe(DatabaseDiagnostic::class);
});

it('filters diagnostics by class name and excludes groups', function (): void {
    $doctor = (new Doctor($this->app))->diagnostics([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
    ]);

    $report = $doctor->run(DiagnosticSelection::make(
        only: ['PassingDiagnostic,DatabaseDiagnostic'],
        except: ['database'],
    ));

    expect($report->diagnostics())->toHaveCount(1)
        ->and($report->diagnostics()[0]->diagnostic::class)->toBe(PassingDiagnostic::class);
});

it('filters diagnostics by resolved package', function (): void {
    $diagnostic = new PackagedDiagnostic;

    expect(DiagnosticSelection::make(only: ['vendor/package'])->matches($diagnostic))->toBeTrue()
        ->and(DiagnosticSelection::make(except: ['vendor/package'])->matches($diagnostic))->toBeFalse();
});

it('filters diagnostics by wildcard package selectors', function (): void {
    $doctor = (new Doctor($this->app))->diagnostics([
        PassingDiagnostic::class,
        PackagedDiagnostic::class,
        LaravelPackageDiagnostic::class,
    ]);

    $report = $doctor->run(DiagnosticSelection::make(only: ['laravel/*']));

    expect($report->diagnostics())->toHaveCount(2)
        ->and($report->diagnostics()[0]->diagnostic::class)->toBe(LaravelPackageDiagnostic::class)
        ->and($report->diagnostics()[1]->diagnostic::class)->toBe(PassingDiagnostic::class);
});

it('filters bundled doctor diagnostics by package selector', function (): void {
    expect(DiagnosticSelection::make(only: ['laravel/doctor'])->matches(new ApplicationKeyIsSet))->toBeTrue()
        ->and(DiagnosticSelection::make(only: ['laravel/*'])->matches(new ApplicationKeyIsSet))->toBeTrue()
        ->and(DiagnosticSelection::make(except: ['laravel/doctor'])->matches(new ApplicationKeyIsSet))->toBeFalse();
});

it('narrows diagnostics with additional only constraints', function (): void {
    $selection = DiagnosticSelection::make(only: ['security'])
        ->constrain(only: ['EnvironmentFileIsGitIgnored']);

    expect($selection->matches(new EnvironmentFileIsGitIgnored))->toBeTrue()
        ->and($selection->matches(new ApplicationKeyIsSet))->toBeFalse();
});

it('stacks additional except selectors', function (): void {
    $selection = DiagnosticSelection::make(except: ['security'])
        ->constrain(except: ['environment']);

    expect($selection->matches(new EnvironmentFileIsGitIgnored))->toBeFalse()
        ->and($selection->matches(new ApplicationKeyIsSet))->toBeFalse()
        ->and($selection->matches(new DatabaseDiagnostic))->toBeTrue();
});
