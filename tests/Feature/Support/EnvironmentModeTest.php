<?php

use Laravel\Doctor\EnvironmentMode;

it('resolves configured environment modes', function (): void {
    config(['doctor.environments' => [
        'local' => ['dev'],
        'production' => ['staging'],
    ]]);

    expect(EnvironmentMode::fromLaravelEnvironment('dev'))->toBe(EnvironmentMode::Local)
        ->and(EnvironmentMode::fromLaravelEnvironment('staging'))->toBe(EnvironmentMode::Production);
});

it('treats unmapped environments as production', function (): void {
    expect(EnvironmentMode::fromLaravelEnvironment('preview'))->toBe(EnvironmentMode::Production);
});

it('rejects invalid configured environment modes', function (): void {
    config(['doctor.environments' => ['lcoal' => ['local']]]);

    EnvironmentMode::fromLaravelEnvironment('local');
})->throws(InvalidArgumentException::class, 'Invalid Doctor environment mode [lcoal] configured.');

it('rejects environment names that are not configured as arrays', function (): void {
    config(['doctor.environments' => ['local' => 'local']]);

    EnvironmentMode::fromLaravelEnvironment('local');
})->throws(InvalidArgumentException::class, 'Laravel environment names for Doctor mode [local] must be an array, [string] given.');

it('rejects environment names assigned to multiple modes', function (): void {
    config(['doctor.environments' => [
        'local' => ['shared'],
        'production' => ['shared'],
    ]]);

    EnvironmentMode::fromLaravelEnvironment('shared');
})->throws(InvalidArgumentException::class, 'Laravel environment [shared] is configured for multiple Doctor modes.');
