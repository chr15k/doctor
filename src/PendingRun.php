<?php

namespace Laravel\Doctor;

use Closure;
use Illuminate\Support\Traits\Conditionable;
use Laravel\Doctor\Results\DiagnosticFixOutcome;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;
use LogicException;

/**
 * @phpstan-type BeforeDiagnostic Closure(Diagnostic): void
 * @phpstan-type AfterDiagnostic Closure(DiagnosticOutcome): void
 * @phpstan-type GroupContinuation Closure(BeforeDiagnostic|null $before, AfterDiagnostic|null $after): void
 */
class PendingRun
{
    use Conditionable;

    /**
     * The callback used to observe each diagnostic group.
     *
     * @var (Closure(string, GroupContinuation): void)|null
     */
    protected ?Closure $observer = null;

    /**
     * The callback that determines whether a failing diagnostic should be fixed.
     *
     * @var (Closure(DiagnosticOutcome): bool)|null
     */
    protected ?Closure $fix = null;

    /**
     * The callback invoked when applied fixes require the diagnostics to be re-run.
     *
     * @var (Closure(): void)|null
     */
    protected ?Closure $beforeRerun = null;

    /**
     * Create a new pending diagnostic run.
     */
    public function __construct(
        protected Doctor $doctor,
        protected DiagnosticSelection $selection,
    ) {
        //
    }

    /**
     * Observe each diagnostic group through the given callback.
     *
     * The callback receives the group name and a controlled continuation that
     * runs the group. The continuation accepts an optional "before" callback
     * invoked before each diagnostic and an optional "after" callback invoked
     * with each outcome.
     *
     * @param  Closure(string, GroupContinuation): void  $callback
     */
    public function observeUsing(Closure $callback): self
    {
        $this->observer = $callback;

        return $this;
    }

    /**
     * Apply fixes approved by the given callback.
     *
     * @param  Closure(DiagnosticOutcome): bool  $callback
     */
    public function fixUsing(Closure $callback): self
    {
        $this->fix = $callback;

        return $this;
    }

    /**
     * Invoke the given callback when applied fixes require the diagnostics to be re-run.
     */
    public function beforeRerun(Closure $callback): self
    {
        $this->beforeRerun = $callback;

        return $this;
    }

    /**
     * Run the diagnostics.
     */
    public function run(): DiagnosticReport
    {
        $report = $this->check();

        if ($this->fix === null) {
            return $report;
        }

        $fixes = $this->fixes($report, $this->fix);

        if ($fixes === []) {
            return $report;
        }

        if ($this->beforeRerun !== null) {
            ($this->beforeRerun)();
        }

        return $this->check()->withFixes($fixes);
    }

    /**
     * Check the selected diagnostics and gather their outcomes into a report.
     */
    protected function check(): DiagnosticReport
    {
        $outcomes = [];

        foreach ($this->doctor->selectedByGroup($this->selection) as $group => $diagnostics) {
            $outcomes = [
                ...$outcomes,
                ...$this->checkGroup($group, $diagnostics),
            ];
        }

        return new DiagnosticReport($outcomes);
    }

    /**
     * Check a diagnostic group while allowing its progress to be observed.
     *
     * @param  list<Diagnostic>  $diagnostics
     * @return list<DiagnosticOutcome>
     */
    protected function checkGroup(string $group, array $diagnostics): array
    {
        if ($this->observer === null) {
            return $this->checkDiagnostics($diagnostics);
        }

        $outcomes = null;

        ($this->observer)($group, function (?Closure $before = null, ?Closure $after = null) use (&$outcomes, $diagnostics): void {
            $outcomes = $this->checkDiagnostics($diagnostics, $before, $after);
        });

        return $outcomes ?? throw new LogicException('A diagnostic group observer must invoke its continuation.');
    }

    /**
     * Check the given diagnostics.
     *
     * @param  list<Diagnostic>  $diagnostics
     * @param  (Closure(Diagnostic): void)|null  $before
     * @param  (Closure(DiagnosticOutcome): void)|null  $after
     * @return list<DiagnosticOutcome>
     */
    protected function checkDiagnostics(array $diagnostics, ?Closure $before = null, ?Closure $after = null): array
    {
        $outcomes = [];

        foreach ($diagnostics as $diagnostic) {
            if ($before !== null) {
                $before($diagnostic);
            }

            $outcome = $this->doctor->check($diagnostic);
            $outcomes[] = $outcome;

            if ($after !== null) {
                $after($outcome);
            }
        }

        return $outcomes;
    }

    /**
     * Apply the approved fixes for the report's failing diagnostics.
     *
     * @param  Closure(DiagnosticOutcome): bool  $fix
     * @return list<DiagnosticFixOutcome>
     */
    protected function fixes(DiagnosticReport $report, Closure $fix): array
    {
        $fixes = [];

        foreach ($report->diagnostics() as $outcome) {
            if (! $outcome->fixable()) {
                continue;
            }

            if ($fix($outcome)) {
                $fixes[] = $this->doctor->fix($outcome);
            }
        }

        return $fixes;
    }
}
