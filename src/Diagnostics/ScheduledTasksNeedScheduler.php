<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class ScheduledTasksNeedScheduler extends Diagnostic
{
    public string $name = 'Scheduled tasks need scheduler';

    public string $group = 'scheduler';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! $this->schedulerIsAvailable()) {
            return DiagnosticResult::skip('The scheduler service is not available.');
        }

        if (! $this->hasScheduledTasks()) {
            return DiagnosticResult::pass('Laravel does not have scheduled tasks.');
        }

        return DiagnosticResult::notice('Laravel has scheduled tasks.')
            ->suggest('Make sure the scheduler is running with `php artisan schedule:run` every minute or `php artisan schedule:work` during development.');
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
