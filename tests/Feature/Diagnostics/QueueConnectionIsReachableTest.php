<?php

use Illuminate\Support\Facades\Schema;
use Laravel\Doctor\Diagnostics\QueueConnectionIsReachable;

it('reports a missing database queue table', function (): void {
    config([
        'queue.default' => 'database',
        'queue.connections.database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'doctor_jobs',
        ],
    ]);

    Schema::dropIfExists('doctor_jobs');

    $result = (new QueueConnectionIsReachable)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('doctor_jobs');
});
