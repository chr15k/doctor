<?php

declare(strict_types=1);

it('loads the package service provider', function (): void {
    expect($this->app->getProvider(\Laravel\Doctor\DoctorServiceProvider::class))->not->toBeNull();
});
