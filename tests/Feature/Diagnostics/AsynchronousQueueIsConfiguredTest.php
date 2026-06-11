<?php

use Laravel\Doctor\Diagnostics\AsynchronousQueueIsConfigured;

it('passes when queues run synchronously', function (): void {
    config(['queue.default' => 'sync']);

    $result = (new AsynchronousQueueIsConfigured)->check();

    expect($result->status->value)->toBe('pass')
        ->and($result->summary)->toBe('Queued jobs run synchronously.');
});

it('notices when queued jobs are processed asynchronously locally', function (): void {
    config([
        'app.env' => 'local',
        'queue.default' => 'database',
    ]);

    $result = (new AsynchronousQueueIsConfigured)->check();

    expect($result->status->value)->toBe('notice')
        ->and($result->summary)->toBe('Queued jobs are processed asynchronously.')
        ->and($result->remediation[0])->toBe('Make sure a queue worker is running with `php artisan queue:work` or Horizon if jobs are not being processed.');
});

it('passes when queued jobs are processed asynchronously outside local environments', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    config(['queue.default' => 'database']);

    $result = (new AsynchronousQueueIsConfigured)->check();

    expect($result->status->value)->toBe('pass')
        ->and($result->summary)->toBe('Queued jobs are processed asynchronously.');
});

it('skips when the default queue connection is not configured', function (): void {
    config(['queue.default' => null]);

    $result = (new AsynchronousQueueIsConfigured)->check();

    expect($result->status->value)->toBe('skip')
        ->and($result->summary)->toBe('Laravel does not have a default queue connection configured.');
});
