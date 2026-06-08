<?php

use Laravel\Doctor\DiagnosticSelection;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\DatabaseDiagnostic;
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
