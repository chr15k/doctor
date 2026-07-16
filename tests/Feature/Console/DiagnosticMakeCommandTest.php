<?php

use App\Doctor\Diagnostics\GeneratedFixableDiagnostic;
use App\Doctor\Diagnostics\GeneratedStandardDiagnostic;
use Illuminate\Support\Facades\File;
use Laravel\Doctor\Results\Status;

afterEach(function (): void {
    File::deleteDirectory(app_path('Doctor'));
});

it('generates a diagnostic class', function (): void {
    $this->artisan('make:diagnostic', ['name' => 'HorizonIsRunning'])
        ->expectsConfirmation('Should the diagnostic offer a fix?')
        ->assertExitCode(0);

    expect(File::get(app_path('Doctor/Diagnostics/HorizonIsRunning.php')))
        ->toContain('namespace App\Doctor\Diagnostics;')
        ->toContain('class HorizonIsRunning extends Diagnostic')
        ->toContain('public function check(): DiagnosticResult')
        ->not->toContain('Fixable');
});

it('generates a fixable diagnostic class', function (): void {
    $this->artisan('make:diagnostic', ['name' => 'HorizonIsRunning', '--fixable' => true])
        ->assertExitCode(0);

    expect(File::get(app_path('Doctor/Diagnostics/HorizonIsRunning.php')))
        ->toContain('use Laravel\Doctor\Contracts\Fixable;')
        ->toContain('class HorizonIsRunning extends Diagnostic implements Fixable')
        ->toContain('->fixable()')
        ->toContain('public function fix(DiagnosticResult $result, ?string $option = null): FixResult');
});

it('prompts for the name and fixability when no input is given', function (): void {
    $this->artisan('make:diagnostic')
        ->expectsQuestion('What should the diagnostic be named?', 'QueueWorkerIsRunning')
        ->expectsConfirmation('Should the diagnostic offer a fix?', 'yes')
        ->assertExitCode(0);

    expect(File::get(app_path('Doctor/Diagnostics/QueueWorkerIsRunning.php')))
        ->toContain('class QueueWorkerIsRunning extends Diagnostic implements Fixable');
});

it('does not prompt for fixability without an interactive terminal', function (): void {
    $this->artisan('make:diagnostic', ['name' => 'MailerIsConfigured', '--no-interaction' => true])
        ->assertExitCode(0);

    expect(File::get(app_path('Doctor/Diagnostics/MailerIsConfigured.php')))
        ->not->toContain('Fixable');
});

it('does not overwrite an existing diagnostic without force', function (): void {
    $this->artisan('make:diagnostic', ['name' => 'ExistingDiagnostic'])
        ->expectsConfirmation('Should the diagnostic offer a fix?')
        ->assertExitCode(0);

    File::put(app_path('Doctor/Diagnostics/ExistingDiagnostic.php'), '<?php // original');

    $this->artisan('make:diagnostic', ['name' => 'ExistingDiagnostic', '--fixable' => true])
        ->expectsOutputToContain('already exists');

    expect(File::get(app_path('Doctor/Diagnostics/ExistingDiagnostic.php')))
        ->toBe('<?php // original');
});

it('generates a diagnostic that runs', function (): void {
    $this->artisan('make:diagnostic', ['name' => 'GeneratedStandardDiagnostic'])
        ->expectsConfirmation('Should the diagnostic offer a fix?')
        ->assertExitCode(0);

    require app_path('Doctor/Diagnostics/GeneratedStandardDiagnostic.php');

    $diagnostic = new GeneratedStandardDiagnostic;

    expect($diagnostic->name)->toBe('Generated standard diagnostic')
        ->and($diagnostic->group)->toBe('generated-standard-diagnostic')
        ->and($diagnostic->check()->status)->toBe(Status::Pass);
});

it('generates a fixable diagnostic that runs and fixes', function (): void {
    $this->artisan('make:diagnostic', ['name' => 'GeneratedFixableDiagnostic', '--fixable' => true])
        ->assertExitCode(0);

    require app_path('Doctor/Diagnostics/GeneratedFixableDiagnostic.php');

    $diagnostic = new GeneratedFixableDiagnostic;
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->fixable)->toBeTrue()
        ->and($result->confirmation)->toBe('Apply the fix?')
        ->and($diagnostic->fix($result)->status)->toBe(Status::Pass);
});
