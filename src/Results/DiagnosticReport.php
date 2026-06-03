<?php

declare(strict_types=1);

namespace Laravel\Doctor\Results;

class DiagnosticReport
{
    /**
     * @param  list<DiagnosticOutcome>  $diagnostics
     * @param  list<DiagnosticFixOutcome>  $fixes
     */
    public function __construct(
        private readonly array $diagnostics = [],
        private readonly array $fixes = [],
    ) {
        //
    }

    /**
     * @return list<DiagnosticOutcome>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @return list<DiagnosticFixOutcome>
     */
    public function fixes(): array
    {
        return $this->fixes;
    }

    /**
     * @param  list<DiagnosticFixOutcome>  $fixes
     */
    public function withFixes(array $fixes): self
    {
        return new self($this->diagnostics, $fixes);
    }

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

    private function hasPassingFixFor(DiagnosticOutcome $diagnostic): bool
    {
        foreach ($this->fixes as $fix) {
            if (
                $fix->registration?->diagnostic === $diagnostic->registration?->diagnostic
                && $fix->result->status === Status::Pass
            ) {
                return true;
            }
        }

        return false;
    }
}
