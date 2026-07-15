<?php

use Laravel\Doctor\Diagnostics\ConfigurationCanBeCached;
use Laravel\Doctor\Results\Link;

it('reports configuration values that cannot be cached', function (): void {
    config(['doctor.unserializable' => fn (): bool => true]);

    $result = (new ConfigurationCanBeCached)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('doctor.unserializable')
        ->and($result->links)->toEqual([Link::docs('configuration', 'configuration-caching')]);
});
