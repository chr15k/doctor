<?php

declare(strict_types=1);

namespace Laravel\Doctor;

use Illuminate\Console\Command;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;
use Laravel\Doctor\Results\Remediation;
use Laravel\Doctor\Results\Status;
use Laravel\Doctor\Support\DiagnosticRegistration;
use Laravel\Doctor\Support\DiagnosticSelection;
use Laravel\Prompts\Support\Logger;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\task;
use function Laravel\Prompts\warning;

class DoctorCommand extends Command
{
    protected $signature = 'doctor
        {--only=* : Run only the specified diagnostic classes, groups, or packages}
        {--except=* : Skip the specified diagnostic classes, groups, or packages}
        {--interactive : Choose diagnostic groups interactively}
        {--fix : Run available deterministic fixes without prompting}
        {--verbose : Show passing and skipped diagnostics too}
        {--format=cli : Output format: cli, json, or github}
        {--fail-on=fail : Exit code threshold: fail, warn, or never}';

    protected $description = 'Run Laravel Doctor diagnostics';

    public function handle(Doctor $doctor): int
    {
        $format = $this->format();

        if ($format === null) {
            return self::FAILURE;
        }

        $selection = $this->selection($doctor);

        $report = $format === 'cli'
            ? $this->runForCli($doctor, $selection)
            : $doctor->run(selection: $selection);

        if ($format === 'cli') {
            $report = $this->applyFixes($doctor, $report);
        }

        if ($format === 'json') {
            $this->line($this->jsonReport($report->diagnostics()));

            return $this->exitCode($report);
        }

        if ($format === 'github') {
            $this->githubReport($report->diagnostics());

            return $this->exitCode($report);
        }

        if ($report->diagnostics() === []) {
            if ($doctor->registry()->registeredDiagnostics() === []) {
                info('No diagnostics registered.');
            } else {
                warning('No diagnostics matched the selected filters.');
            }

            return self::SUCCESS;
        }

        $this->cliReport($report);

        return $this->exitCode($report);
    }

    private function format(): ?string
    {
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['cli', 'json', 'github'], true)) {
            $this->error('The --format option must be one of: cli, json, github.');

            return null;
        }

        return $format;
    }

    private function selection(Doctor $doctor): DiagnosticSelection
    {
        $selection = DiagnosticSelection::make(
            only: $this->optionList('only'),
            except: $this->optionList('except'),
        );

        if (! $this->option('interactive') || ! $this->input->isInteractive()) {
            return $selection;
        }

        $groups = $this->availableGroups($doctor);

        if ($groups === []) {
            return $selection;
        }

        $selected = multiselect(
            label: 'Which diagnostic groups should run?',
            options: $groups,
            default: array_values(array_intersect($selection->only, array_keys($groups))),
            hint: 'Leave empty to run every registered group.',
        );

        if ($selected === []) {
            return $selection;
        }

        return DiagnosticSelection::make(
            only: array_map(strval(...), $selected),
            except: $selection->except,
        );
    }

    /**
     * @return list<string>
     */
    private function optionList(string $name): array
    {
        $value = $this->option($name);

        if (! is_array($value)) {
            return is_string($value) && $value !== '' ? [$value] : [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @return array<string, string>
     */
    private function availableGroups(Doctor $doctor): array
    {
        $groups = [];

        foreach ($doctor->registry()->registeredDiagnostics() as $registration) {
            $definition = $doctor->diagnosticRunner()->definition($registration);
            $groups[$definition->group] = ucfirst($definition->group);
        }

        ksort($groups);

        return $groups;
    }

    private function runForCli(Doctor $doctor, DiagnosticSelection $selection): DiagnosticReport
    {
        if (! $this->shouldUseTasks()) {
            return $doctor->run(selection: $selection);
        }

        $outcomes = [];

        foreach ($this->groupRegistrations($doctor, $selection) as $group => $registrations) {
            $outcomes = [
                ...$outcomes,
                ...task(
                    label: sprintf('Running %s diagnostics...', ucfirst($group)),
                    keepSummary: true,
                    callback: fn (Logger $logger): array => $this->runTaskGroup($doctor, $registrations, $logger),
                ),
            ];
        }

        return new DiagnosticReport($outcomes);
    }

    private function shouldUseTasks(): bool
    {
        return function_exists('Laravel\Prompts\task')
            && function_exists('pcntl_fork')
            && $this->output->isDecorated()
            && $this->input->isInteractive();
    }

    /**
     * @return array<string, list<DiagnosticRegistration>>
     */
    private function groupRegistrations(Doctor $doctor, DiagnosticSelection $selection): array
    {
        $groups = [];

        foreach ($doctor->diagnosticRunner()->orderedRegistrations($doctor->registry()->registeredDiagnostics()) as $registration) {
            $definition = $doctor->diagnosticRunner()->definition($registration);

            if (! $selection->matches($definition, $registration)) {
                continue;
            }

            $groups[$definition->group] ??= [];
            $groups[$definition->group][] = $registration;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @param  list<DiagnosticRegistration>  $registrations
     * @return list<DiagnosticOutcome>
     */
    private function runTaskGroup(Doctor $doctor, array $registrations, Logger $logger): array
    {
        $outcomes = [];

        foreach ($registrations as $registration) {
            $definition = $doctor->diagnosticRunner()->definition($registration);

            $logger->subLabel($definition->name);

            $outcome = $doctor->diagnosticRunner()->runRegistration($registration, $definition);

            match ($outcome->result->status) {
                Status::Pass => $logger->success($definition->name),
                Status::Warn => $logger->warning($definition->name.': '.$outcome->result->summary),
                Status::Skip => $logger->line($definition->name.': '.$outcome->result->summary),
                Status::Fail, Status::Error => $logger->error($definition->name.': '.$outcome->result->summary),
            };

            $outcomes[] = $outcome;
        }

        $logger->subLabel('');

        return $outcomes;
    }

    private function cliReport(DiagnosticReport $report): void
    {
        $verbose = (bool) $this->option('verbose');

        foreach ($report->diagnostics() as $outcome) {
            if (! $verbose && ($outcome->result->status === Status::Pass || $outcome->result->status === Status::Skip)) {
                continue;
            }

            $this->line(sprintf(
                '[%s] %s (%s): %s',
                $outcome->result->status->value,
                $outcome->definition->name,
                $outcome->source(),
                $outcome->result->summary,
            ));

            $this->renderDiagnosticDetails($outcome);
        }

        foreach ($report->fixes() as $outcome) {
            $this->line(sprintf(
                '[%s] fix %s (%s): %s',
                $outcome->result->status->value,
                $outcome->definition->name,
                $outcome->source(),
                $outcome->result->summary,
            ));
        }

        if ($report->hasFailures()) {
            error('Doctor found failing diagnostics.');

            return;
        }

        if ($report->hasWarnings()) {
            warning('Doctor found warnings.');

            return;
        }

        if ($report->fixes() !== []) {
            info('All diagnostics passed or were fixed.');

            return;
        }

        info('All diagnostics passed.');
    }

    private function applyFixes(Doctor $doctor, DiagnosticReport $report): DiagnosticReport
    {
        $fixes = [];

        foreach ($report->diagnostics() as $outcome) {
            if (! $outcome->result->status->failed() || $outcome->registration === null) {
                continue;
            }

            if (! $doctor->diagnosticRunner()->isFixable($outcome->registration)) {
                continue;
            }

            $prompt = $doctor->diagnosticRunner()->fixPrompt($outcome->registration);

            $this->line(sprintf(
                'Fix available for %s (%s): %s',
                $outcome->definition->name,
                $outcome->source(),
                $outcome->result->summary,
            ));

            $this->renderDiagnosticDetails($outcome);

            if (! $this->shouldApplyFix($prompt)) {
                continue;
            }

            $fixes[] = $doctor->diagnosticRunner()->fixRegistration(
                $outcome->registration,
                $outcome->result,
                $outcome->definition,
            );
        }

        return $report->withFixes($fixes);
    }

    private function renderDiagnosticDetails(DiagnosticOutcome $outcome): void
    {
        if ($outcome->result->details !== null) {
            $this->line('    '.$outcome->result->details);
        }

        foreach ($outcome->result->remediation as $remediation) {
            $this->line('    '.$this->formatRemediation($remediation));
        }
    }

    private function formatRemediation(Remediation $remediation): string
    {
        if ($remediation->isCommand()) {
            return sprintf('Run `%s` - %s', $remediation->command, $remediation->message);
        }

        return $remediation->message;
    }

    private function shouldApplyFix(string $prompt): bool
    {
        if ((bool) $this->option('fix')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm($prompt, default: true);
    }

    /**
     * @param  list<DiagnosticOutcome>  $diagnostics
     */
    private function jsonReport(array $diagnostics): string
    {
        $encoded = json_encode([
            'version' => 1,
            'diagnostics' => array_map(
                static fn (DiagnosticOutcome $outcome) => [
                    'class' => $outcome->registration?->diagnostic,
                    'group' => $outcome->definition->group,
                    'name' => $outcome->definition->name,
                    'status' => $outcome->result->status->value,
                    'summary' => $outcome->result->summary,
                    'details' => $outcome->result->details,
                    'remediation' => array_map(
                        static fn (Remediation $remediation) => [
                            'message' => $remediation->message,
                            'command' => $remediation->command,
                        ],
                        $outcome->result->remediation,
                    ),
                ],
                $diagnostics,
            ),
        ], JSON_THROW_ON_ERROR);

        return $encoded;
    }

    /**
     * @param  list<DiagnosticOutcome>  $diagnostics
     */
    private function githubReport(array $diagnostics): void
    {
        foreach ($diagnostics as $outcome) {
            if ($outcome->result->status === Status::Pass || $outcome->result->status === Status::Skip) {
                continue;
            }

            $level = $outcome->result->status === Status::Warn ? 'warning' : 'error';

            $this->line(sprintf(
                '::%s title=%s::%s',
                $level,
                $this->escapeGithubCommand($outcome->definition->name),
                $this->escapeGithubCommand($this->githubSummary($outcome)),
            ));
        }
    }

    private function githubSummary(DiagnosticOutcome $outcome): string
    {
        $parts = [$outcome->result->summary];

        if ($outcome->result->details !== null) {
            $parts[] = $outcome->result->details;
        }

        foreach ($outcome->result->remediation as $remediation) {
            $parts[] = $this->formatRemediation($remediation);
        }

        return implode(' ', $parts);
    }

    private function exitCode(DiagnosticReport $report): int
    {
        $failOn = $this->option('fail-on');

        if (! is_string($failOn) || ! in_array($failOn, [Status::Fail->value, Status::Warn->value, 'never'], true)) {
            $this->error('The --fail-on option must be one of: fail, warn, never.');

            return self::FAILURE;
        }

        if ($failOn === 'never') {
            return self::SUCCESS;
        }

        if ($failOn === Status::Warn->value) {
            return $report->hasWarnings() ? self::FAILURE : self::SUCCESS;
        }

        return $report->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function escapeGithubCommand(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n", ':', ','],
            ['%25', '%0D', '%0A', '%3A', '%2C'],
            $value,
        );
    }
}
