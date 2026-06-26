<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;

class ScheduledTasksNeedScheduler extends Diagnostic
{
    public string $name = 'Scheduled tasks need scheduler';

    public string $group = 'scheduler';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'scheduler-missing' => Outcome::skip('The scheduler service is not available.'),
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
        if (! $this->schedulerIsAvailable()) {
            return $this->result('scheduler-missing');
        }

        if (! $this->hasScheduledTasks()) {
            return $this->result('no-tasks');
        }

        return $this->result('tasks-registered');
    }

    /**
     * Determine whether Laravel's scheduler service is available.
     */
    private function schedulerIsAvailable(): bool
    {
        return app()->bound(Schedule::class);
    }

    /**
     * Determine whether scheduled tasks are registered.
     */
    private function hasScheduledTasks(): bool
    {
        return $this->events() !== [];
    }

    /**
     * Get registered scheduled events.
     *
     * @return list<Event>
     */
    private function events(): array
    {
        return array_values(app(Schedule::class)->events());
    }
}
