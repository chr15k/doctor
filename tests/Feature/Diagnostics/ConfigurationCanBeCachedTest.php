<?php

use Laravel\Doctor\Diagnostics\ConfigurationCanBeCached;

it('reports configuration values that cannot be cached', function (): void {
    config(['doctor.unserializable' => fn (): bool => true]);

    $result = (new ConfigurationCanBeCached)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('doctor.unserializable');
});
