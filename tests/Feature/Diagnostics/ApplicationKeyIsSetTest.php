<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostics\ApplicationKeyIsSet;
use Laravel\Doctor\DiagnosticSelection;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticResult;

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
        ->and($outcome->result->summary)->toBe('Laravel does not have an application key.')
        ->and($outcome->result->confirmation)->toBe('Would you like Doctor to generate an application key using `artisan key:generate`?')
        ->and($outcome->result->remediation[0])->toBe('Generate an application key with `php artisan key:generate`.');
});

it('generates an application key when fixed', function (): void {
    Process::fake([
        '*' => Process::result(output: 'Application key set successfully.'),
    ]);

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        new ApplicationKeyIsSet,
        DiagnosticResult::fail('Laravel does not have an application key.'),
    ));

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'key:generate']
        && $process->path === base_path());

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->summary)->toBe('The application key was generated.');
});

it('reports when application key generation fails', function (): void {
    Process::fake([
        '*' => Process::result(errorOutput: 'Unable to write key.', exitCode: 1),
    ]);

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        new ApplicationKeyIsSet,
        DiagnosticResult::fail('Laravel does not have an application key.'),
    ));

    expect($fix->result->status->value)->toBe('fail')
        ->and($fix->result->summary)->toBe('The application key could not be generated.')
        ->and($fix->result->details)->toBe('Unable to write key.');
});
