<?php

declare(strict_types=1);

use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Support\DiagnosticRegistration;
use Laravel\Doctor\Support\DiagnosticRegistry;
use Laravel\Doctor\Support\DiagnosticRunner;
use Laravel\Doctor\Support\DiagnosticSelection;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\FixableDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PassingDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\ThrowingDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\ThrowingFixDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\WarningDiagnostic;

it('runs registered diagnostics', function (): void {
    $this->app->make(DiagnosticRegistry::class)
        ->diagnostics([
            PassingDiagnostic::class,
            WarningDiagnostic::class,
        ]);

    $report = $this->app->make(DiagnosticRunner::class)
        ->run(selection: DiagnosticSelection::make(only: ['testing']));

    expect($report->diagnostics())->toHaveCount(2)
        ->and($report->hasWarnings())->toBeTrue()
        ->and($report->hasFailures())->toBeFalse()
        ->and($report->diagnostics()[1]->result->remediation[0]->command)->toBe('php artisan doctor --only=WarningDiagnostic');
});

it('runs internal diagnostics before package diagnostics', function (): void {
    $registrations = [
        new class(WarningDiagnostic::class) extends DiagnosticRegistration
        {
            public function package(): ?string
            {
                return 'vendor/package';
            }
        },
        new DiagnosticRegistration(PassingDiagnostic::class),
    ];

    $report = $this->app->make(DiagnosticRunner::class)
        ->runRegistrations($registrations, selection: DiagnosticSelection::make(only: ['testing']));

    expect($report->diagnostics())->toHaveCount(2)
        ->and($report->diagnostics()[0]->registration?->diagnostic)->toBe(PassingDiagnostic::class)
        ->and($report->diagnostics()[1]->registration?->diagnostic)->toBe(WarningDiagnostic::class);
});

it('converts diagnostic exceptions into error results', function (): void {
    $this->app->make(DiagnosticRegistry::class)
        ->diagnostic(ThrowingDiagnostic::class);

    $report = $this->app->make(DiagnosticRunner::class)
        ->run(selection: DiagnosticSelection::make(only: ['ThrowingDiagnostic']));
    $outcome = $report->diagnostics()[0];

    expect($outcome->registration?->diagnostic)->toBe(ThrowingDiagnostic::class)
        ->and($outcome->result->status->value)->toBe('error')
        ->and($outcome->result->summary)->toBe('The diagnostic exploded.')
        ->and($report->hasFailures())->toBeTrue();
});

it('runs a fix supplied by the diagnostic', function (): void {
    $registry = $this->app->make(DiagnosticRegistry::class)
        ->diagnostic(FixableDiagnostic::class);

    $runner = $this->app->make(DiagnosticRunner::class);
    $registration = array_values(array_filter(
        $registry->registeredDiagnostics(),
        static fn (DiagnosticRegistration $registration): bool => $registration->diagnostic === FixableDiagnostic::class,
    ))[0];

    $outcome = $runner->run(selection: DiagnosticSelection::make(only: ['FixableDiagnostic']))
        ->diagnostics()[0];

    expect($runner->isFixable($registration))->toBeTrue()
        ->and($runner->fixPrompt($registration))->toBe('Fix the testing diagnostic?');

    $fix = $runner->fixRegistration($registration, $outcome->result);

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->summary)->toBe('The diagnostic was fixed.');
});

it('turns fix exceptions into helpful error results', function (): void {
    $registry = $this->app->make(DiagnosticRegistry::class)
        ->diagnostic(ThrowingFixDiagnostic::class);
    $registration = array_values(array_filter(
        $registry->registeredDiagnostics(),
        static fn (DiagnosticRegistration $registration): bool => $registration->diagnostic === ThrowingFixDiagnostic::class,
    ))[0];

    $fix = $this->app->make(DiagnosticRunner::class)
        ->fixRegistration($registration, DiagnosticResult::fail('The diagnostic failed.'));

    expect($fix->result->status->value)->toBe('error')
        ->and($fix->result->summary)->toBe('Failed to fix Testing diagnostic fix throws: permission denied');
});
