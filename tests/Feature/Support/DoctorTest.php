<?php

use Illuminate\Console\Command;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Diagnostics\ApplicationKeyIsSet;
use Laravel\Doctor\DiagnosticSelection;
use Laravel\Doctor\DiagnosticSource;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\Results\DiagnosticFixOutcome;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\DatabaseDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\DefaultMetadataDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\FixableDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\NoticeDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PackagedDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PassingDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\ThrowingDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\ThrowingFixDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\WarningDiagnostic;

it('registers diagnostics', function (): void {
    $doctor = new Doctor($this->app);

    $doctor->diagnostic(PassingDiagnostic::class);

    expect($doctor->registered())->toBe([PassingDiagnostic::class])
        ->and($doctor->hasDiagnostics())->toBeTrue();
});

it('rejects classes that are not diagnostics', function (): void {
    (new Doctor($this->app))->diagnostic(stdClass::class);
})->throws(InvalidArgumentException::class);

it('resolves diagnostic sources from the class file', function (): void {
    expect(DiagnosticSource::resolve(PassingDiagnostic::class)->package)->toBe('laravel/doctor')
        ->and(DiagnosticSource::resolve(Command::class)->package)->toBe('laravel/framework')
        ->and(DiagnosticSource::resolve(PassingDiagnostic::class)->relativeFile)->toBe('tests/Fixtures/Diagnostics/PassingDiagnostic.php')
        ->and(DiagnosticSource::resolve(PassingDiagnostic::class)->application)->toBeTrue()
        ->and(DiagnosticSource::resolve(ApplicationKeyIsSet::class)->application)->toBeFalse()
        ->and(DiagnosticSource::for(new PassingDiagnostic)->label())->toBe('laravel/doctor')
        ->and((new PassingDiagnostic)->package())->toBe('laravel/doctor')
        ->and(DiagnosticSource::for(new PassingDiagnostic)->application)->toBeTrue()
        ->and(DiagnosticSource::for(new PackagedDiagnostic)->label())->toBe('vendor/package')
        ->and(DiagnosticSource::for(new PackagedDiagnostic)->application)->toBeFalse();
});

it('runs registered diagnostics', function (): void {
    $doctor = (new Doctor($this->app))->diagnostics([
        PassingDiagnostic::class,
        WarningDiagnostic::class,
    ]);

    $report = $doctor->run(DiagnosticSelection::make(only: ['testing']));

    expect($report->diagnostics())->toHaveCount(2)
        ->and($report->hasWarnings())->toBeTrue()
        ->and($report->hasFailures())->toBeFalse()
        ->and($report->diagnostics()[1]->result->remediation)->toBe('Re-run this diagnostic after addressing the warning.');
});

it('runs diagnostics in first-seen group order', function (): void {
    $doctor = (new Doctor($this->app))->diagnostics([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
        WarningDiagnostic::class,
    ]);

    $groups = $doctor->selectedByGroup();
    $expected = [
        PassingDiagnostic::class,
        WarningDiagnostic::class,
        DatabaseDiagnostic::class,
    ];

    expect(array_keys($groups))->toBe(['testing', 'database'])
        ->and(array_map(
            static fn (Diagnostic $diagnostic): string => $diagnostic::class,
            [...$groups['testing'], ...$groups['database']],
        ))->toBe($expected)
        ->and(array_map(
            static fn (DiagnosticOutcome $outcome): string => $outcome->diagnostic::class,
            $doctor->run()->diagnostics(),
        ))->toBe($expected);
});

it('does not treat notices as warnings', function (): void {
    $doctor = (new Doctor($this->app))->diagnostic(NoticeDiagnostic::class);

    $report = $doctor->run();

    expect($report->diagnostics()[0]->result->status->value)->toBe('notice')
        ->and($report->hasWarnings())->toBeFalse()
        ->and($report->hasFailures())->toBeFalse();
});

it('defaults diagnostic metadata from the class name', function (): void {
    $diagnostic = new DefaultMetadataDiagnostic;

    expect($diagnostic->name)->toBe('Default metadata diagnostic')
        ->and($diagnostic->group)->toBe('default-metadata-diagnostic');
});

it('runs package diagnostics before application diagnostics', function (): void {
    $doctor = (new Doctor($this->app))->diagnostics([
        PassingDiagnostic::class,
        PackagedDiagnostic::class,
    ]);

    $report = $doctor->run();

    expect($report->diagnostics())->toHaveCount(2)
        ->and($report->diagnostics()[0]->diagnostic::class)->toBe(PackagedDiagnostic::class)
        ->and($report->diagnostics()[1]->diagnostic::class)->toBe(PassingDiagnostic::class);
});

it('converts diagnostic exceptions into error results', function (): void {
    $doctor = (new Doctor($this->app))->diagnostic(ThrowingDiagnostic::class);

    $report = $doctor->run();
    $outcome = $report->diagnostics()[0];

    expect($outcome->diagnostic::class)->toBe(ThrowingDiagnostic::class)
        ->and($outcome->result->status->value)->toBe('error')
        ->and($outcome->result->summary)->toBe('The diagnostic exploded.')
        ->and($report->hasFailures())->toBeTrue();
});

it('runs a fix supplied by the diagnostic', function (): void {
    $doctor = (new Doctor($this->app))->diagnostic(FixableDiagnostic::class);

    $outcome = $doctor->run()->diagnostics()[0];

    expect($outcome->diagnostic)->toBeInstanceOf(Fixable::class)
        ->and($outcome->result->confirmation)->toBe('Fix the testing diagnostic?')
        ->and($outcome->result->remediation)->toBe('Apply the testing diagnostic fix.');

    $fix = $doctor->fix($outcome);

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->summary)->toBe('The diagnostic was fixed.');
});

it('turns fix exceptions into helpful error results', function (): void {
    $fix = (new Doctor($this->app))->fix(new DiagnosticOutcome(
        new ThrowingFixDiagnostic,
        DiagnosticResult::fail('The diagnostic failed.'),
    ));

    expect($fix->result->status->value)->toBe('error')
        ->and($fix->result->summary)->toBe('Failed to fix Testing diagnostic fix throws: permission denied');
});

it('rejects fixes for diagnostics that are not fixable', function (): void {
    (new Doctor($this->app))->fix(new DiagnosticOutcome(
        new PassingDiagnostic,
        DiagnosticResult::fail('The diagnostic failed.'),
    ));
})->throws(LogicException::class);

it('appends fixes to an existing diagnostic report', function (): void {
    $diagnostic = new PassingDiagnostic;
    $first = new DiagnosticFixOutcome($diagnostic, FixResult::pass('The first fix passed.'));
    $second = new DiagnosticFixOutcome($diagnostic, FixResult::pass('The second fix passed.'));

    $report = (new DiagnosticReport)->withFixes([$first])->withFixes([$second]);

    expect($report->fixes())->toBe([$first, $second]);
});
