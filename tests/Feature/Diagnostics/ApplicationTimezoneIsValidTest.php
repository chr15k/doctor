<?php

use Laravel\Doctor\Diagnostics\ApplicationTimezoneIsValid;

it('reports an invalid application timezone', function (): void {
    config(['app.timezone' => 'Invalid/Timezone']);

    $result = (new ApplicationTimezoneIsValid)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('Invalid/Timezone');
});
