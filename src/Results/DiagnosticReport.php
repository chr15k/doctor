<?php

namespace Laravel\Doctor\Results;

class DiagnosticReport
{
    /**
     * Create a new diagnostic report instance.
     *
     * @param  list<DiagnosticOutcome>  $diagnostics
     * @param  list<DiagnosticFixOutcome>  $fixes
     */
    public function __construct(
        protected array $diagnostics = [],
        protected array $fixes = [],
    ) {
        //
    }

    /**
     * Get the diagnostic outcomes.
     *
     * @return list<DiagnosticOutcome>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * Get the diagnostic fix outcomes.
     *
     * @return list<DiagnosticFixOutcome>
     */
    public function fixes(): array
    {
        return $this->fixes;
    }

    /**
     * Add fix outcomes to the report.
     *
     * @param  list<DiagnosticFixOutcome>  $fixes
     */
    public function withFixes(array $fixes): self
    {
        return new self($this->diagnostics, [...$this->fixes, ...$fixes]);
    }

    /**
     * Determine whether the report has failures.
     */
    public function hasFailures(): bool
    {
        foreach ($this->diagnostics as $outcome) {
            if ($this->hasPassingFixFor($outcome)) {
                continue;
            }

            if ($outcome->result->status->failed()) {
                return true;
            }
        }

        foreach ($this->fixes as $outcome) {
            if ($outcome->result->status->failed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the report has warnings.
     */
    public function hasWarnings(): bool
    {
        foreach ($this->diagnostics as $outcome) {
            if ($this->hasPassingFixFor($outcome)) {
                continue;
            }

            if ($outcome->result->status === Status::Warn || $outcome->result->status->failed()) {
                return true;
            }
        }

        foreach ($this->fixes as $outcome) {
            if ($outcome->result->status === Status::Warn || $outcome->result->status->failed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the diagnostic has a passing fix.
     */
    public function hasPassingFixFor(DiagnosticOutcome $diagnostic): bool
    {
        foreach ($this->fixes as $fix) {
            if ($fix->diagnostic === $diagnostic->diagnostic && $fix->result->status === Status::Pass) {
                return true;
            }
        }

        return false;
    }
}
