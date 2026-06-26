<?php

use Laravel\Doctor\Diagnostics\DebugModeMatchesEnvironment;

it('reports debug mode in production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    config(['app.debug' => true]);

    $result = (new DebugModeMatchesEnvironment)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->summary)->toBe('Laravel debug mode is enabled in production.');
});
