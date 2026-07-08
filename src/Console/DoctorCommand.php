<?php

namespace Laravel\Doctor\Console;

use Closure;
use Illuminate\Console\Command;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\DiagnosticSelection;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\PendingRun;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;
use Laravel\Doctor\Results\Status;
use Laravel\Prompts\Support\Logger;

use function Laravel\Prompts\confirm;
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
     * The interactive CLI renderer.
     */
    protected ?CliRenderer $renderer = null;

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
        $report = $doctor->runner($selection)
            ->when($this->shouldUseTasks(), fn (PendingRun $runner) => $runner->through($this->taskRunner()))
            ->fixUsing(fn (DiagnosticOutcome $outcome): bool => $this->shouldApplyFix($outcome))
            ->beforeRerun(fn () => info('Re-running diagnostics after applying fixes...'))
            ->run();

        if ($report->diagnostics() === []) {
            if (! $doctor->hasDiagnostics()) {
                info('No diagnostics registered.');
            } else {
                warning('No diagnostics matched the selected filters.');
            }

            return self::SUCCESS;
        }

        $this->renderer()->render($report);

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

        $this->line(json_encode($report, JSON_THROW_ON_ERROR));

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

        foreach ((new GithubRenderer)->annotations($report) as $annotation) {
            $this->line($annotation);
        }

        return $this->exitCode($report, $failOn);
    }

    /**
     * Build the diagnostic selection from command options.
     */
    protected function selection(Doctor $doctor): DiagnosticSelection
    {
        $selection = $doctor->defaultSelection()->constrain(
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
     * Determine whether Laravel Prompts tasks should be used.
     */
    protected function shouldUseTasks(): bool
    {
        return function_exists('pcntl_fork')
            && $this->output->isDecorated()
            && $this->input->isInteractive();
    }

    /**
     * Get the callback that runs each diagnostic group as a console task.
     */
    protected function taskRunner(): Closure
    {
        return fn (string $group, Closure $run): array => task(
            label: sprintf('Running %s diagnostics...', ucfirst($group)),
            limit: 0,
            keepSummary: true,
            callback: fn (Logger $logger): array => $this->runGroupTask($run, $logger),
        );
    }

    /**
     * Run a diagnostic group within a console task.
     *
     * @param  Closure(?Closure, ?Closure): list<DiagnosticOutcome>  $run
     * @return list<DiagnosticOutcome>
     */
    protected function runGroupTask(Closure $run, Logger $logger): array
    {
        $outcomes = $run(
            fn (Diagnostic $diagnostic) => $logger->subLabel($diagnostic->name),
            fn (DiagnosticOutcome $outcome) => $this->logOutcome($logger, $outcome),
        );

        $logger->subLabel('');

        return $outcomes;
    }

    /**
     * Write a diagnostic outcome to the task logger.
     */
    protected function logOutcome(Logger $logger, DiagnosticOutcome $outcome): void
    {
        $name = $outcome->diagnostic->name;

        match ($outcome->result->status) {
            Status::Pass => $logger->success($name),
            Status::Notice, Status::Skip => $logger->line($name.': '.$outcome->result->summary),
            Status::Warn => $logger->warning($name.': '.$outcome->result->summary),
            Status::Fail, Status::Error => $logger->error($name.': '.$outcome->result->summary),
        };
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

        $this->renderer()->renderFixConfirmation($outcome);

        return confirm($this->confirmationPrompt($outcome), default: true);
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
     * Get the interactive CLI renderer.
     */
    protected function renderer(): CliRenderer
    {
        return $this->renderer ??= new CliRenderer($this->output);
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
            return $report->hasFailures() || $report->hasWarnings() ? self::FAILURE : self::SUCCESS;
        }

        return $report->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
