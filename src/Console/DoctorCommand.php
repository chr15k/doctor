<?php

namespace Laravel\Doctor\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\DiagnosticSelection;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\Results\DiagnosticFixOutcome;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;
use Laravel\Doctor\Results\Status;
use Laravel\Prompts\Elements\Element;
use Laravel\Prompts\Elements\ElementContract;
use Laravel\Prompts\Support\Logger;

use function Laravel\Prompts\callout;
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
        {--format=cli : Output format: cli, json, or github}
        {--fail-on=fail : Exit code threshold: fail, warn, or never}';

    protected $description = 'Run Laravel Doctor diagnostics';

    /**
     * Execute the console command.
     */
    public function handle(Doctor $doctor): int
    {
        $format = $this->format();
        $failOn = $this->failOn();

        if ($format === null || $failOn === null || ! $this->fixOptionIsValidFor($format)) {
            return self::FAILURE;
        }

        $selection = $this->selection($doctor);

        return match ($format) {
            'cli' => $this->runCli($doctor, $selection, $failOn),
            'json' => $this->runJson($doctor, $selection, $failOn),
            'github' => $this->runGithub($doctor, $selection, $failOn),
        };
    }

    /**
     * Get the requested output format.
     *
     * @return 'cli'|'json'|'github'|null
     */
    protected function format(): ?string
    {
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['cli', 'json', 'github'], true)) {
            $this->error('The --format option must be one of: cli, json, github.');

            return null;
        }

        return $format;
    }

    /**
     * Get the configured exit-code threshold.
     *
     * @return 'fail'|'warn'|'never'|null
     */
    protected function failOn(): ?string
    {
        $failOn = $this->option('fail-on');

        if (! is_string($failOn) || ! in_array($failOn, [Status::Fail->value, Status::Warn->value, 'never'], true)) {
            $this->error('The --fail-on option must be one of: fail, warn, never.');

            return null;
        }

        return $failOn;
    }

    /**
     * Determine whether the fix option is valid for the format.
     *
     * @param  'cli'|'json'|'github'  $format
     */
    protected function fixOptionIsValidFor(string $format): bool
    {
        if ($format === 'cli' || ! (bool) $this->option('fix')) {
            return true;
        }

        $this->error('The --fix option may only be used with --format=cli.');

        return false;
    }

    /**
     * Run Doctor for CLI output.
     *
     * @param  'fail'|'warn'|'never'  $failOn
     */
    protected function runCli(Doctor $doctor, DiagnosticSelection $selection, string $failOn): int
    {
        $report = $this->applyFixes($doctor, $selection, $this->runForCli($doctor, $selection));

        if ($report->diagnostics() === []) {
            if (! $doctor->hasDiagnostics()) {
                info('No diagnostics registered.');
            } else {
                warning('No diagnostics matched the selected filters.');
            }

            return self::SUCCESS;
        }

        $this->cliReport($report);

        return $this->exitCode($report, $failOn);
    }

    /**
     * Run Doctor for JSON output.
     *
     * @param  'fail'|'warn'|'never'  $failOn
     */
    protected function runJson(Doctor $doctor, DiagnosticSelection $selection, string $failOn): int
    {
        $report = $doctor->run($selection);

        $this->line($this->jsonReport($report->diagnostics()));

        return $this->exitCode($report, $failOn);
    }

    /**
     * Run Doctor for GitHub Actions output.
     *
     * @param  'fail'|'warn'|'never'  $failOn
     */
    protected function runGithub(Doctor $doctor, DiagnosticSelection $selection, string $failOn): int
    {
        $report = $doctor->run($selection);

        $this->githubReport($report->diagnostics());

        return $this->exitCode($report, $failOn);
    }

    /**
     * Build the diagnostic selection from command options.
     */
    protected function selection(Doctor $doctor): DiagnosticSelection
    {
        $selection = DiagnosticSelection::make(
            only: $this->configList('only'),
            except: $this->configList('except'),
        )->constrain(
            only: $this->optionList('only'),
            except: $this->optionList('except'),
        );

        if (! $this->option('interactive') || ! $this->input->isInteractive()) {
            return $selection;
        }

        $groups = $doctor->availableGroups($selection);

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

        return $selection->constrain(
            only: array_map(strval(...), $selected),
        );
    }

    /**
     * Get a normalized list option.
     *
     * @return list<string>
     */
    protected function optionList(string $name): array
    {
        return array_values(array_filter((array) $this->option($name), is_string(...)));
    }

    /**
     * Get a normalized list configuration value.
     *
     * @return list<string>
     */
    protected function configList(string $name): array
    {
        return array_values(array_filter(config()->array('doctor.'.$name, []), is_string(...)));
    }

    /**
     * Run diagnostics for CLI output.
     */
    protected function runForCli(Doctor $doctor, DiagnosticSelection $selection): DiagnosticReport
    {
        if (! $this->shouldUseTasks()) {
            return $doctor->run($selection);
        }

        $outcomes = [];

        foreach ($doctor->selectedByGroup($selection) as $group => $diagnostics) {
            $outcomes = [
                ...$outcomes,
                ...task(
                    label: sprintf('Running %s diagnostics...', ucfirst($group)),
                    limit: 0,
                    keepSummary: true,
                    callback: fn (Logger $logger): array => $this->runTaskGroup($doctor, $diagnostics, $logger),
                ),
            ];
        }

        return new DiagnosticReport($outcomes);
    }

    /**
     * Determine whether Laravel Prompts tasks should be used.
     */
    protected function shouldUseTasks(): bool
    {
        return function_exists('pcntl_fork')
            && $this->output->isDecorated()
            && $this->input->isInteractive();
    }

    /**
     * Run a grouped set of diagnostics inside a task.
     *
     * @param  list<Diagnostic>  $diagnostics
     * @return list<DiagnosticOutcome>
     */
    protected function runTaskGroup(Doctor $doctor, array $diagnostics, Logger $logger): array
    {
        $outcomes = [];

        foreach ($diagnostics as $diagnostic) {
            $logger->subLabel($diagnostic->name);

            $outcome = $doctor->check($diagnostic);

            match ($outcome->result->status) {
                Status::Pass => $logger->success($diagnostic->name),
                Status::Notice => $logger->line($diagnostic->name.': '.$outcome->result->summary),
                Status::Warn => $logger->warning($diagnostic->name.': '.$outcome->result->summary),
                Status::Skip => $logger->line($diagnostic->name.': '.$outcome->result->summary),
                Status::Fail, Status::Error => $logger->error($diagnostic->name.': '.$outcome->result->summary),
            };

            $outcomes[] = $outcome;
        }

        $logger->subLabel('');

        return $outcomes;
    }

    /**
     * Render the CLI report.
     */
    protected function cliReport(DiagnosticReport $report): void
    {
        $verbose = $this->output->isVerbose();
        $notices = [];

        foreach ($report->diagnostics() as $outcome) {
            if ($outcome->result->status === Status::Notice) {
                $notices[] = $outcome;

                continue;
            }

            if ($this->isReportableIssue($outcome)) {
                $this->renderIssue($outcome);

                continue;
            }

            if (! $verbose && in_array($outcome->result->status, [Status::Pass, Status::Skip], true)) {
                continue;
            }

            $this->line(sprintf(
                '[%s] %s (%s): %s',
                $outcome->result->status->value,
                $outcome->diagnostic->name,
                $outcome->diagnostic->source(),
                $outcome->result->summary,
            ));

            $this->renderDiagnosticDetails($outcome);
        }

        $this->renderNotices($notices);
        $this->renderFixes($report->fixes());

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

    /**
     * Apply accepted diagnostic fixes.
     */
    protected function applyFixes(Doctor $doctor, DiagnosticSelection $selection, DiagnosticReport $report): DiagnosticReport
    {
        $fixes = [];

        foreach ($report->diagnostics() as $outcome) {
            if (! $outcome->result->status->failed()) {
                continue;
            }

            if (! $outcome->diagnostic instanceof Fixable) {
                continue;
            }

            if (! $this->shouldApplyFix($outcome)) {
                continue;
            }

            $fixes[] = $doctor->fix($outcome);
        }

        if ($fixes === []) {
            return $report;
        }

        info('Re-running diagnostics after applying fixes...');

        return $this->runForCli($doctor, $selection)->withFixes($fixes);
    }

    /**
     * Render diagnostic details, remediation, and links.
     */
    protected function renderDiagnosticDetails(DiagnosticOutcome $outcome): void
    {
        if ($outcome->result->details !== null) {
            $this->line('    '.$outcome->result->details);
        }

        if ($outcome->result->remediation !== null) {
            $this->line('    '.$outcome->result->remediation);
        }

        foreach ($outcome->result->links as $label => $url) {
            $this->line(sprintf('    %s: %s', $label, $url));
        }
    }

    /**
     * Render an issue diagnostic as a callout.
     */
    protected function renderIssue(DiagnosticOutcome $outcome): void
    {
        callout(
            label: $outcome->diagnostic->name,
            content: $this->diagnosticCalloutContent($outcome),
            type: $outcome->result->status === Status::Warn ? 'warning' : 'error',
            info: $outcome->diagnostic->source(),
        );
    }

    /**
     * Determine whether the diagnostic should be displayed as an issue.
     */
    protected function isReportableIssue(DiagnosticOutcome $outcome): bool
    {
        return in_array($outcome->result->status, [Status::Warn, Status::Fail, Status::Error], true);
    }

    /**
     * Render notice diagnostics as callouts grouped by source.
     *
     * @param  list<DiagnosticOutcome>  $notices
     */
    protected function renderNotices(array $notices): void
    {
        if ($notices === []) {
            return;
        }

        foreach ($this->noticesBySource($notices) as $source => $sourceNotices) {
            callout(
                label: Str::plural('Notice', count($sourceNotices)),
                content: $this->noticesCalloutContent($sourceNotices),
                info: $source,
            );
        }
    }

    /**
     * @param  list<DiagnosticOutcome>  $notices
     * @return array<string, list<DiagnosticOutcome>>
     */
    protected function noticesBySource(array $notices): array
    {
        $grouped = [];

        foreach ($notices as $notice) {
            $grouped[$notice->diagnostic->source()][] = $notice;
        }

        return $grouped;
    }

    /**
     * Format notice content for a callout.
     *
     * @param  list<DiagnosticOutcome>  $notices
     * @return list<string|ElementContract>
     */
    protected function noticesCalloutContent(array $notices): array
    {
        $content = count($notices) === 1
            ? [$this->noticeItem($notices[0])]
            : [Element::bulletedList(array_map(
                fn (DiagnosticOutcome $notice): string => $this->noticeItem($notice),
                $notices,
            ), spaced: true)];

        $links = $this->noticeLinks($notices);

        if ($links !== []) {
            $content[] = Element::heading('Links');
            $content[] = Element::keyValueList($links);
        }

        return $content;
    }

    /**
     * Format a notice as a compact list item.
     */
    protected function noticeItem(DiagnosticOutcome $outcome): string
    {
        $parts = [$outcome->result->summary];

        if ($outcome->result->details !== null) {
            $parts[] = $outcome->result->details;
        }

        if ($outcome->result->remediation !== null) {
            $parts[] = $outcome->result->remediation;
        }

        return Str::squish(implode(' ', $parts));
    }

    /**
     * @param  list<DiagnosticOutcome>  $notices
     * @return array<string, string>
     */
    protected function noticeLinks(array $notices): array
    {
        $links = [];

        foreach ($notices as $notice) {
            $links = [
                ...$links,
                ...$notice->result->links,
            ];
        }

        return $links;
    }

    /**
     * Render diagnostic fixes as callouts grouped by source.
     *
     * @param  list<DiagnosticFixOutcome>  $fixes
     */
    protected function renderFixes(array $fixes): void
    {
        if ($fixes === []) {
            return;
        }

        foreach ($this->fixesBySource($fixes) as $source => $sourceFixes) {
            callout(
                label: Str::plural('Fix', count($sourceFixes)),
                content: $this->fixesCalloutContent($sourceFixes),
                type: $this->fixesCalloutType($sourceFixes),
                info: $source,
            );
        }
    }

    /**
     * @param  list<DiagnosticFixOutcome>  $fixes
     * @return array<string, list<DiagnosticFixOutcome>>
     */
    protected function fixesBySource(array $fixes): array
    {
        $grouped = [];

        foreach ($fixes as $fix) {
            $grouped[$fix->diagnostic->source()][] = $fix;
        }

        return $grouped;
    }

    /**
     * Format fix content for a callout.
     *
     * @param  list<DiagnosticFixOutcome>  $fixes
     * @return list<string|ElementContract>
     */
    protected function fixesCalloutContent(array $fixes): array
    {
        if (count($fixes) === 1) {
            return [$this->fixItem($fixes[0])];
        }

        return [Element::bulletedList(array_map(
            fn (DiagnosticFixOutcome $fix): string => $this->fixItem($fix),
            $fixes,
        ), spaced: true)];
    }

    /**
     * Format a fix outcome as a compact list item.
     */
    protected function fixItem(DiagnosticFixOutcome $outcome): string
    {
        $parts = [
            sprintf('%s: %s', $outcome->diagnostic->name, $outcome->result->summary),
        ];

        if ($outcome->result->details !== null) {
            $parts[] = $outcome->result->details;
        }

        return Str::squish(implode(' ', $parts));
    }

    /**
     * @param  list<DiagnosticFixOutcome>  $fixes
     */
    protected function fixesCalloutType(array $fixes): ?string
    {
        foreach ($fixes as $fix) {
            if ($fix->result->status->failed()) {
                return 'error';
            }
        }

        foreach ($fixes as $fix) {
            if ($fix->result->status === Status::Warn) {
                return 'warning';
            }
        }

        return null;
    }

    /**
     * Format diagnostic content for a callout.
     *
     * @return list<string|ElementContract>
     */
    protected function diagnosticCalloutContent(DiagnosticOutcome $outcome, bool $includeRemediation = true): array
    {
        $content = [$outcome->result->summary];

        if ($outcome->result->details !== null) {
            $content[] = $outcome->result->details;
        }

        if ($includeRemediation && $outcome->result->remediation !== null) {
            $content[] = Element::heading('Suggested fix');
            $content[] = $outcome->result->remediation;
        }

        if ($outcome->result->links !== []) {
            $content[] = Element::heading('Links');
            $content[] = Element::keyValueList($outcome->result->links);
        }

        return $content;
    }

    /**
     * Get the confirmation prompt for a diagnostic fix.
     */
    protected function confirmationPrompt(DiagnosticOutcome $outcome): string
    {
        return $outcome->result->confirmation
            ?? sprintf('Would you like Doctor to fix "%s"?', $outcome->diagnostic->name);
    }

    /**
     * Render a diagnostic fix confirmation as a callout.
     */
    protected function renderFixConfirmation(DiagnosticOutcome $outcome): void
    {
        callout(
            label: $outcome->diagnostic->name,
            content: $this->fixConfirmationCalloutContent($outcome),
            type: $outcome->result->status === Status::Warn ? 'warning' : 'error',
            info: $outcome->diagnostic->source(),
        );
    }

    /**
     * Format fix confirmation content for a callout.
     *
     * @return list<string|ElementContract>
     */
    protected function fixConfirmationCalloutContent(DiagnosticOutcome $outcome): array
    {
        return $this->diagnosticCalloutContent($outcome, includeRemediation: false);
    }

    /**
     * Determine whether a fix should be applied.
     */
    protected function shouldApplyFix(DiagnosticOutcome $outcome): bool
    {
        if ((bool) $this->option('fix')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        $this->renderFixConfirmation($outcome);

        return confirm($this->confirmationPrompt($outcome), default: true);
    }

    /**
     * Render diagnostics as JSON.
     *
     * @param  list<DiagnosticOutcome>  $diagnostics
     */
    protected function jsonReport(array $diagnostics): string
    {
        return json_encode([
            'version' => 1,
            'diagnostics' => array_map(
                fn (DiagnosticOutcome $outcome) => [
                    'class' => $outcome->diagnostic::class,
                    'group' => $outcome->diagnostic->group,
                    'name' => $outcome->diagnostic->name,
                    'source' => $this->jsonSource($outcome),
                    'code' => $outcome->result->code,
                    'status' => $outcome->result->status->value,
                    'summary' => $outcome->result->summary,
                    'details' => $outcome->result->details,
                    'remediation' => $outcome->result->remediation,
                    'links' => (object) $outcome->result->links,
                ],
                $diagnostics,
            ),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Render diagnostics as GitHub Actions annotations.
     *
     * @param  list<DiagnosticOutcome>  $diagnostics
     */
    protected function githubReport(array $diagnostics): void
    {
        foreach ($diagnostics as $outcome) {
            if ($outcome->result->status === Status::Pass || $outcome->result->status === Status::Skip) {
                continue;
            }

            $level = $outcome->result->status === Status::Notice ? 'notice' : ($outcome->result->status === Status::Warn ? 'warning' : 'error');

            $this->line(sprintf(
                '::%s title=%s::%s',
                $level,
                $this->escapeGithubCommandProperty($this->githubTitle($outcome)),
                $this->escapeGithubCommandData($this->githubSummary($outcome)),
            ));
        }
    }

    /**
     * Build a JSON source payload.
     *
     * @return array{label: string, package: string|null, file: string|null, application: bool}
     */
    protected function jsonSource(DiagnosticOutcome $outcome): array
    {
        return [
            'label' => $outcome->diagnostic->source(),
            'package' => $outcome->diagnostic->package(),
            'file' => $outcome->diagnostic->diagnosticSource()->relativeFile,
            'application' => $outcome->diagnostic->application(),
        ];
    }

    /**
     * Build a GitHub annotation title.
     */
    protected function githubTitle(DiagnosticOutcome $outcome): string
    {
        return sprintf('%s (%s)', $outcome->diagnostic->name, $outcome->diagnostic->source());
    }

    /**
     * Build a GitHub annotation summary.
     */
    protected function githubSummary(DiagnosticOutcome $outcome): string
    {
        $parts = [$outcome->result->summary];

        if ($outcome->result->details !== null) {
            $parts[] = $outcome->result->details;
        }

        if ($outcome->result->remediation !== null) {
            $parts[] = $outcome->result->remediation;
        }

        foreach ($outcome->result->links as $label => $url) {
            $parts[] = sprintf('%s: %s', $label, $url);
        }

        return implode(' ', $parts);
    }

    /**
     * Determine the command exit code for the report.
     *
     * @param  'fail'|'warn'|'never'  $failOn
     */
    protected function exitCode(DiagnosticReport $report, string $failOn): int
    {
        if ($failOn === 'never') {
            return self::SUCCESS;
        }

        if ($failOn === Status::Warn->value) {
            return $report->hasWarnings() ? self::FAILURE : self::SUCCESS;
        }

        return $report->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Escape a GitHub workflow command property value.
     */
    protected function escapeGithubCommandProperty(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n", ':', ','],
            ['%25', '%0D', '%0A', '%3A', '%2C'],
            $value,
        );
    }

    /**
     * Escape a GitHub workflow command message body.
     */
    protected function escapeGithubCommandData(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n"],
            ['%25', '%0D', '%0A'],
            $value,
        );
    }
}
