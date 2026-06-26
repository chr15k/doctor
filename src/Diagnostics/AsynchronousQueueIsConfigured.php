<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;

class AsynchronousQueueIsConfigured extends Diagnostic
{
    public string $name = 'Asynchronous queue is configured';

    public string $group = 'queue';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-configured' => Outcome::skip('Laravel does not have a default queue connection configured.'),
            'sync-production' => Outcome::warn(
                summary: 'Queued jobs run synchronously in production.',
                remediation: 'Set QUEUE_CONNECTION to a background queue driver such as database, redis, sqs, or beanstalkd.',
            ),
            'sync-local' => Outcome::pass('Queued jobs run synchronously.'),
            'async' => Outcome::pass('Queued jobs are processed asynchronously.'),
            'async-local' => Outcome::notice(
                summary: 'Queued jobs are processed asynchronously.',
                remediation: 'Make sure a queue worker is running with `php artisan queue:work` or Horizon if jobs are not being processed.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $connection = config('queue.default');

        if (! is_string($connection) || $connection === '') {
            return $this->result('not-configured');
        }

        if ($connection === 'sync' && ! app()->environment(['local', 'testing'])) {
            return $this->result('sync-production');
        }

        if ($connection === 'sync') {
            return $this->result('sync-local');
        }

        if (! app()->environment(['local', 'testing'])) {
            return $this->result('async');
        }

        return $this->result('async-local');
    }
}
