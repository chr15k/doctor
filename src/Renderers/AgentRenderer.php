<?php

namespace Laravel\Doctor\Renderers;

use Laravel\Doctor\Results\DiagnosticFixOutcome;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;
use Laravel\Doctor\Results\Status;

class AgentRenderer
{
    /**
     * Render the report as a single line of agent-optimized JSON.
     */
    public function render(DiagnosticReport $report): string
    {
        return json_encode($this->payload($report), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Build the agent-optimized report payload.
     *
     * Follows the agent-optimized output convention established by Laravel
     * PAO: aggregate counts up front and only actionable outcomes itemized,
     * so agents get a minimal, parseable report.
     *
     * @return array<string, mixed>
     */
    protected function payload(DiagnosticReport $report): array
    {
        $counts = array_fill_keys(array_column(Status::cases(), 'value'), 0);
        $issues = [];

        foreach ($report->diagnostics() as $outcome) {
            $counts[$outcome->result->status->value]++;

            if ($outcome->result->status !== Status::Pass && $outcome->result->status !== Status::Skip) {
                $issues[] = $this->issue($outcome);
            }
        }

        return array_filter([
            'tool' => 'doctor',
            'result' => $report->hasFailures() ? 'failed' : 'passed',
            'diagnostics' => array_sum($counts),
            'failed' => $counts[Status::Fail->value] + $counts[Status::Error->value],
            'warnings' => $counts[Status::Warn->value],
            'notices' => $counts[Status::Notice->value],
            'passed' => $counts[Status::Pass->value],
            'skipped' => $counts[Status::Skip->value],
            'issues' => $issues,
            'fixes' => array_map($this->fix(...), $report->fixes()),
        ], static fn (mixed $value): bool => $value !== []);
    }

    /**
     * Build the payload entry for an actionable diagnostic outcome.
     *
     * @return array<string, mixed>
     */
    protected function issue(DiagnosticOutcome $outcome): array
    {
        return array_filter([
            'name' => $outcome->diagnostic->name,
            'status' => $outcome->result->status->value,
            'summary' => $outcome->result->summary,
            'details' => $outcome->result->details,
            'fix' => $outcome->result->remediation,
            'fixable' => $outcome->fixable() ?: null,
            'links' => array_column($outcome->result->links, 'agentUrl', 'label'),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Build the payload entry for an applied fix.
     *
     * @return array<string, mixed>
     */
    protected function fix(DiagnosticFixOutcome $outcome): array
    {
        return array_filter([
            'name' => $outcome->diagnostic->name,
            'status' => $outcome->result->status->value,
            'summary' => $outcome->result->summary,
            'details' => $outcome->result->details,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
