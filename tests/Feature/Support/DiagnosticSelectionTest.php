<?php

declare(strict_types=1);

use Laravel\Doctor\Support\DiagnosticRegistration;
use Laravel\Doctor\Support\DiagnosticRegistry;
use Laravel\Doctor\Support\DiagnosticRunner;
use Laravel\Doctor\Support\DiagnosticSelection;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\DatabaseDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PassingDiagnostic;

it('filters diagnostics by group', function (): void {
    $registry = (new DiagnosticRegistry)
        ->diagnostics([
            PassingDiagnostic::class,
            DatabaseDiagnostic::class,
        ]);

    $report = $this->app->make(DiagnosticRunner::class)
        ->runRegistrations(
            $registry->registeredDiagnostics(),
            selection: DiagnosticSelection::make(only: ['database']),
        );

    expect($report->diagnostics())->toHaveCount(1)
        ->and($report->diagnostics()[0]->registration?->diagnostic)->toBe(DatabaseDiagnostic::class);
});

it('filters diagnostics by class name and excludes groups', function (): void {
    $this->app->make(DiagnosticRegistry::class)
        ->diagnostics([
            PassingDiagnostic::class,
            DatabaseDiagnostic::class,
        ]);

    $report = $this->app->make(DiagnosticRunner::class)
        ->runRegistrations(
            $this->app->make(DiagnosticRegistry::class)->registeredDiagnostics(),
            selection: DiagnosticSelection::make(
                only: ['PassingDiagnostic,DatabaseDiagnostic'],
                except: ['database'],
            ),
        );

    expect($report->diagnostics())->toHaveCount(1)
        ->and($report->diagnostics()[0]->registration?->diagnostic)->toBe(PassingDiagnostic::class);
});

it('filters diagnostics by resolved package', function (): void {
    $registration = new class(PassingDiagnostic::class) extends DiagnosticRegistration
    {
        public function package(): ?string
        {
            return 'vendor/package';
        }
    };

    $definition = $this->app->make(DiagnosticRunner::class)->definition($registration);

    expect(DiagnosticSelection::make(only: ['vendor/package'])->matches($definition, $registration))->toBeTrue()
        ->and(DiagnosticSelection::make(except: ['vendor/package'])->matches($definition, $registration))->toBeFalse();
});
