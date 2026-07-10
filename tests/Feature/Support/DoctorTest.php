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
use Laravel\Doctor\Tests\Fixtures\Diagnostics\FixesSharedStateDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\NoticeDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PackagedDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PassingDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\SharedStateWasFixedDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\ThrowingDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\ThrowingFixableDiagnostic;
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
        ->and(DiagnosticSource::resolve(Command::class)->application)->toBeFalse()
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

it('runs diagnostics in registration order', function (): void {
    $doctor = (new Doctor($this->app))->diagnostics([
        PassingDiagnostic::class,
        PackagedDiagnostic::class,
    ]);

    $report = $doctor->run();

    expect($report->diagnostics())->toHaveCount(2)
        ->and($report->diagnostics()[0]->diagnostic::class)->toBe(PassingDiagnostic::class)
        ->and($report->diagnostics()[1]->diagnostic::class)->toBe(PackagedDiagnostic::class);
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
        ->and($outcome->fixable())->toBeTrue()
        ->and($outcome->result->confirmation)->toBe('Fix the testing diagnostic?')
        ->and($outcome->result->remediation)->toBe('Apply the testing diagnostic fix.');

    $fix = $doctor->fix($outcome);

    expect($fix->result->status->value)->toBe('pass')
        ->and($fix->result->summary)->toBe('The diagnostic was fixed.');
});

it('turns fix exceptions into helpful error results', function (): void {
    $fix = (new Doctor($this->app))->fix(new DiagnosticOutcome(
        new ThrowingFixDiagnostic,
        DiagnosticResult::fail('The diagnostic failed.')->fixable(),
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

it('rejects fixes for outcomes that are not explicitly fixable', function (): void {
    (new Doctor($this->app))->fix(new DiagnosticOutcome(
        new FixableDiagnostic,
        DiagnosticResult::fail('The diagnostic failed.'),
    ));
})->throws(LogicException::class, 'is not fixable');

it('rejects fixes for error outcomes even when marked fixable', function (): void {
    (new Doctor($this->app))->fix(new DiagnosticOutcome(
        new FixableDiagnostic,
        DiagnosticResult::error('The diagnostic errored.')->fixable(),
    ));
})->throws(LogicException::class, 'is not fixable');

it('does not offer an automatic fix after a fixable diagnostic throws', function (): void {
    $doctor = (new Doctor($this->app))->diagnostic(ThrowingFixableDiagnostic::class);
    $offered = false;

    $report = $doctor->runner()
        ->fixUsing(function (DiagnosticOutcome $outcome) use (&$offered): bool {
            $offered = true;

            return true;
        })
        ->run();

    expect($report->diagnostics()[0]->result->status->value)->toBe('error')
        ->and($report->diagnostics()[0]->fixable())->toBeFalse()
        ->and($report->fixes())->toBe([])
        ->and($offered)->toBeFalse();
});

it('applies configured selectors to programmatic runs', function (): void {
    config(['doctor.only' => ['database']]);

    $doctor = (new Doctor($this->app))->diagnostics([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
    ]);

    $report = $doctor->run();

    expect($report->diagnostics())->toHaveCount(1)
        ->and($report->diagnostics()[0]->diagnostic::class)->toBe(DatabaseDiagnostic::class);
});

it('applies approved fixes and re-runs the diagnostics', function (): void {
    config(['doctor-testing.shared-state-fixed' => false]);

    $doctor = (new Doctor($this->app))->diagnostics([
        FixesSharedStateDiagnostic::class,
        SharedStateWasFixedDiagnostic::class,
    ]);

    $rerunning = false;

    $report = $doctor->runner()
        ->fixUsing(fn (DiagnosticOutcome $outcome): bool => true)
        ->beforeRerun(function () use (&$rerunning): void {
            $rerunning = true;
        })
        ->run();

    expect($report->fixes())->toHaveCount(1)
        ->and($report->fixes()[0]->result->summary)->toBe('The shared state fix ran.')
        ->and($report->hasFailures())->toBeFalse()
        ->and($rerunning)->toBeTrue();
});

it('does not re-run diagnostics when fixes are declined', function (): void {
    config(['doctor-testing.shared-state-fixed' => false]);

    $doctor = (new Doctor($this->app))->diagnostic(FixesSharedStateDiagnostic::class);

    $rerunning = false;

    $report = $doctor->runner()
        ->fixUsing(fn (DiagnosticOutcome $outcome): bool => false)
        ->beforeRerun(function () use (&$rerunning): void {
            $rerunning = true;
        })
        ->run();

    expect($report->fixes())->toBe([])
        ->and($report->hasFailures())->toBeTrue()
        ->and($rerunning)->toBeFalse();
});

it('owns diagnostic execution while allowing progress to be observed', function (): void {
    $doctor = (new Doctor($this->app))->diagnostics([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
        ThrowingDiagnostic::class,
    ]);

    $observed = [];

    $report = $doctor->runner()
        ->observeUsing(function (string $group, Closure $next) use (&$observed): void {
            $observed[] = 'group:'.$group;

            $next(
                before: function (Diagnostic $diagnostic) use (&$observed): void {
                    $observed[] = 'checking:'.class_basename($diagnostic);
                },
                after: function (DiagnosticOutcome $outcome) use (&$observed): void {
                    $observed[] = 'result:'.$outcome->result->status->value;
                },
            );
        })
        ->run();

    expect($report->diagnostics())->toHaveCount(3)
        ->and($report->diagnostics()[1]->result->status->value)->toBe('error')
        ->and($observed)->toBe([
            'group:testing',
            'checking:PassingDiagnostic',
            'result:pass',
            'checking:ThrowingDiagnostic',
            'result:error',
            'group:database',
            'checking:DatabaseDiagnostic',
            'result:pass',
        ]);
});

it('requires progress observers to continue runner-owned execution', function (): void {
    (new Doctor($this->app))
        ->diagnostic(PassingDiagnostic::class)
        ->runner()
        ->observeUsing(function (string $group, Closure $next): void {
            // Intentionally do not continue the runner-owned execution.
        })
        ->run();
})->throws(LogicException::class, 'must invoke its continuation');

it('appends fixes to an existing diagnostic report', function (): void {
    $diagnostic = new PassingDiagnostic;
    $first = new DiagnosticFixOutcome($diagnostic, FixResult::pass('The first fix passed.'));
    $second = new DiagnosticFixOutcome($diagnostic, FixResult::pass('The second fix passed.'));

    $report = (new DiagnosticReport)->withFixes([$first])->withFixes([$second]);

    expect($report->fixes())->toBe([$first, $second]);
});
