<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;

class ScheduledTasksRequireScheduler extends Diagnostic
{
    public string $name = 'Scheduled tasks require scheduler';

    public string $group = 'scheduler';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'no-tasks' => Outcome::pass('Laravel does not have scheduled tasks.'),
            'tasks-registered' => Outcome::notice(
                summary: 'Laravel has scheduled tasks.',
                remediation: 'Make sure the scheduler is running with `php artisan schedule:run` every minute or `php artisan schedule:work` during development.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (app(Schedule::class)->events() === []) {
            return $this->result('no-tasks');
        }

        return $this->result('tasks-registered');
    }
}
