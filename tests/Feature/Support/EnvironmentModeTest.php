<?php

use Laravel\Doctor\EnvironmentMode;

it('resolves configured environment modes', function (): void {
    config(['doctor.environments' => ['dev' => 'local', 'staging' => 'production']]);

    expect(EnvironmentMode::fromLaravelEnvironment('dev'))->toBe(EnvironmentMode::Local)
        ->and(EnvironmentMode::fromLaravelEnvironment('staging'))->toBe(EnvironmentMode::Production);
});

it('treats unmapped environments as production', function (): void {
    expect(EnvironmentMode::fromLaravelEnvironment('staging'))->toBe(EnvironmentMode::Production);
});

it('rejects invalid configured environment modes', function (): void {
    config(['doctor.environments' => ['local' => 'lcoal']]);

    EnvironmentMode::fromLaravelEnvironment('local');
})->throws(InvalidArgumentException::class, 'Invalid Doctor environment mode [lcoal] configured for the [local] environment.');
