<?php

use Illuminate\Support\Facades\Artisan;
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
    $artisan = new class
    {
        public array $calls = [];

        public function call(string $command): int
        {
            $this->calls[] = $command;

            return 0;
        }
    };

    Artisan::swap($artisan);

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        new ApplicationKeyIsSet,
        DiagnosticResult::fail('Laravel does not have an application key.'),
    ));

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->summary)->toBe('The application key was generated.')
        ->and($artisan->calls)->toBe(['key:generate']);
});

it('reports when application key generation fails', function (): void {
    Artisan::swap(new class
    {
        public function call(string $command): int
        {
            return 1;
        }

        public function output(): string
        {
            return 'Unable to write key.';
        }
    });

    $fix = $this->app->make(Doctor::class)->fix(new DiagnosticOutcome(
        new ApplicationKeyIsSet,
        DiagnosticResult::fail('Laravel does not have an application key.'),
    ));

    expect($fix->result->status->value)->toBe('fail')
        ->and($fix->result->summary)->toBe('The application key could not be generated.')
        ->and($fix->result->details)->toBe('Unable to write key.');
});
