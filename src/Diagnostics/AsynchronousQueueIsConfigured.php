<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class AsynchronousQueueIsConfigured extends Diagnostic
{
    public string $name = 'Asynchronous queue is configured';

    public string $group = 'queue';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $connection = config('queue.default');

        if (! is_string($connection) || $connection === '') {
            return DiagnosticResult::skip('Laravel does not have a default queue connection configured.');
        }

        if ($connection === 'sync') {
            return DiagnosticResult::pass('Queued jobs run synchronously.');
        }

        if (! app()->environment(['local', 'testing'])) {
            return DiagnosticResult::pass('Queued jobs are processed asynchronously.');
        }

        return DiagnosticResult::notice('Queued jobs are processed asynchronously.')
            ->suggest('If jobs are not being processed be sure to start your queue worker.');
    }
}
