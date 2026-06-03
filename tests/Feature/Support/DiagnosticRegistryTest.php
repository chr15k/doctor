<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Laravel\Doctor\Support\DiagnosticRegistry;
use Laravel\Doctor\Support\DiagnosticSource;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PassingDiagnostic;

it('registers diagnostics', function (): void {
    $registry = new DiagnosticRegistry;

    $registry->diagnostic(PassingDiagnostic::class);

    expect($registry->diagnosticClasses())->toBe([PassingDiagnostic::class]);
});

it('stores registered diagnostics without package metadata', function (): void {
    $registry = new DiagnosticRegistry;

    $registry->diagnostics([PassingDiagnostic::class]);

    $diagnostic = $registry->registeredDiagnostics()[0];

    expect($diagnostic->diagnostic)->toBe(PassingDiagnostic::class)
        ->and($diagnostic->source())->toBe('internal');
});

it('resolves diagnostic source from the class file', function (): void {
    expect(DiagnosticSource::package(PassingDiagnostic::class))->toBeNull()
        ->and(DiagnosticSource::package(Command::class))->toBe('laravel/framework');
});
