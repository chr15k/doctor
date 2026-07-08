<?php

namespace Laravel\Doctor;

use Closure;
use Illuminate\Support\Traits\Conditionable;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Results\DiagnosticFixOutcome;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;

class PendingRun
{
    use Conditionable;

    /**
     * The callback used to execute each diagnostic group.
     *
     * @var (Closure(string, Closure): list<DiagnosticOutcome>)|null
     */
    protected ?Closure $through = null;

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
     * Execute each diagnostic group through the given callback.
     *
     * The callback receives the group name and a closure that checks the
     * group's diagnostics and returns their outcomes. The closure accepts
     * two optional observers, invoked before and after each diagnostic.
     *
     * @param  Closure(string, Closure): list<DiagnosticOutcome>  $callback
     */
    public function through(Closure $callback): self
    {
        $this->through = $callback;

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
            $run = fn (?Closure $checking = null, ?Closure $checked = null): array => $this->checkEach(
                $diagnostics, $checking, $checked,
            );

            $outcomes = [
                ...$outcomes,
                ...($this->through !== null ? ($this->through)($group, $run) : $run()),
            ];
        }

        return new DiagnosticReport($outcomes);
    }

    /**
     * Check the given diagnostics.
     *
     * @param  list<Diagnostic>  $diagnostics
     * @param  (Closure(Diagnostic): void)|null  $checking
     * @param  (Closure(DiagnosticOutcome): void)|null  $checked
     * @return list<DiagnosticOutcome>
     */
    protected function checkEach(array $diagnostics, ?Closure $checking, ?Closure $checked): array
    {
        $outcomes = [];

        foreach ($diagnostics as $diagnostic) {
            if ($checking !== null) {
                $checking($diagnostic);
            }

            $outcome = $this->doctor->check($diagnostic);

            if ($checked !== null) {
                $checked($outcome);
            }

            $outcomes[] = $outcome;
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
            if (! $outcome->result->status->failed() || ! $outcome->diagnostic instanceof Fixable) {
                continue;
            }

            if ($fix($outcome)) {
                $fixes[] = $this->doctor->fix($outcome);
            }
        }

        return $fixes;
    }
}
