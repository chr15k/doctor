<?php

namespace Laravel\Doctor\Console;

use Closure;
use Illuminate\Console\Command;
use Laravel\AgentDetector\AgentDetector;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\PendingRun;
use Laravel\Doctor\Renderers\AgentRenderer;
use Laravel\Doctor\Renderers\CliRenderer;
use Laravel\Doctor\Renderers\GithubRenderer;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;
use Laravel\Doctor\Results\Status;
use Laravel\Prompts\Support\Logger;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\task;
use function Laravel\Prompts\warning;

/**
 * @phpstan-import-type GroupContinuation from PendingRun
 */
class DoctorCommand extends Command
{
    protected $signature = 'doctor
        {--only=* : Run only the specified diagnostic classes, groups, or packages}
        {--except=* : Skip the specified diagnostic classes, groups, or packages}
        {--bail : Stop after the first failing diagnostic}
        {--fix : Run available deterministic fixes without prompting}
        {--format=cli : Output format: cli, json, github, or agent}
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

        return match ($format) {
            'cli' => $this->runCli($doctor, $failOn),
            'json' => $this->runJson($doctor, $failOn),
            'github' => $this->runGithub($doctor, $failOn),
            'agent' => $this->runAgent($doctor, $failOn),
        };
    }

    /**
     * Get the requested output format.
     *
     * @return 'cli'|'json'|'github'|'agent'|null
     */
    protected function format(): ?string
    {
        // TODO: Use $this->input()->string() and friends for option retrieval once
        // Laravel 12 support is dropped (CommandInput requires laravel/framework 13.19+)...
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['cli', 'json', 'github', 'agent'], true)) {
            $this->error('The --format option must be one of: cli, json, github, agent.');

            return null;
        }

        if (! $this->input->hasParameterOption('--format') && AgentDetector::detect()->isAgent) {
            return 'agent';
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
     * @param  'cli'|'json'|'github'|'agent'  $format
     */
    protected function fixOptionIsValidFor(string $format): bool
    {
        if ($format === 'cli' || $format === 'agent' || ! (bool) $this->option('fix')) {
            return true;
        }

        $this->error('The --fix option may only be used with the cli or agent output formats.');

        return false;
    }

    /**
     * Run Doctor for CLI output.
     *
     * @param  'fail'|'warn'|'never'  $failOn
     */
    protected function runCli(Doctor $doctor, string $failOn): int
    {
        $report = $this->runner($doctor)
            ->when($this->shouldUseTasks(), fn (PendingRun $runner) => $runner->observeUsing($this->taskObserver()))
            ->fixUsing(fn (DiagnosticOutcome $outcome): bool => $this->shouldApplyFix($outcome))
            ->beforeRerun(function (): void {
                $this->restorePrompts();

                info('Re-running diagnostics after applying fixes...');
            })
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
    protected function runJson(Doctor $doctor, string $failOn): int
    {
        $report = $this->runner($doctor)->run();

        $this->line(json_encode($report, JSON_THROW_ON_ERROR));

        return $this->exitCode($report, $failOn);
    }

    /**
     * Run Doctor for GitHub Actions output.
     *
     * @param  'fail'|'warn'|'never'  $failOn
     */
    protected function runGithub(Doctor $doctor, string $failOn): int
    {
        $report = $this->runner($doctor)->run();

        foreach ((new GithubRenderer)->annotations($report) as $annotation) {
            $this->line($annotation);
        }

        return $this->exitCode($report, $failOn);
    }

    /**
     * Run Doctor for agent-optimized output.
     *
     * @param  'fail'|'warn'|'never'  $failOn
     */
    protected function runAgent(Doctor $doctor, string $failOn): int
    {
        $report = $this->runner($doctor)
            ->when((bool) $this->option('fix'), fn (PendingRun $runner) => $runner->fixUsing(static fn (): bool => true))
            ->run();

        $this->line((new AgentRenderer)->render($report));

        return $this->exitCode($report, $failOn);
    }

    /**
     * Begin a run configured from the command options.
     */
    protected function runner(Doctor $doctor): PendingRun
    {
        return $doctor->only(...$this->optionList('only'))
            ->except(...$this->optionList('except'))
            ->bail((bool) $this->option('bail'));
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
     * Get the callback that observes each diagnostic group as a console task.
     *
     * @return Closure(string, GroupContinuation): void
     */
    protected function taskObserver(): Closure
    {
        return function (string $group, Closure $next): void {
            task(
                label: sprintf('%s diagnostics', ucfirst($group)),
                limit: 0,
                keepSummary: true,
                callback: fn (Logger $logger) => $this->observeGroupTask($next, $logger),
            );
        };
    }

    /**
     * Observe a diagnostic group within a console task.
     *
     * @param  GroupContinuation  $next
     */
    protected function observeGroupTask(Closure $next, Logger $logger): void
    {
        $next(
            before: function (Diagnostic $diagnostic) use ($logger): void {
                $logger->subLabel($diagnostic->name);
            },
            after: function (DiagnosticOutcome $outcome) use ($logger): void {
                $this->logOutcome($logger, $outcome);
            },
        );

        $logger->subLabel('');
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

        $this->restorePrompts();

        $this->renderer()->renderFixConfirmation($outcome);

        return confirm($this->confirmationPrompt($outcome), default: true);
    }

    /**
     * Restore the prompts configuration.
     *
     * Fixes may run nested Artisan commands, which re-bind the global prompts
     * output and fallbacks to the nested command. Without a restore, Doctor's
     * remaining output is swallowed and pending confirmations appear to hang.
     */
    protected function restorePrompts(): void
    {
        $this->configurePrompts($this->input);
    }

    /**
     * Get the confirmation prompt for a diagnostic fix.
     */
    protected function confirmationPrompt(DiagnosticOutcome $outcome): string
    {
        return $outcome->result->confirmation
            ?? sprintf('Fix "%s"?', $outcome->diagnostic->name);
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
