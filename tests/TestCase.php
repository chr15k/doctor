<?php

namespace Laravel\Doctor\Tests;

use Laravel\Doctor\DoctorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DoctorServiceProvider::class,
        ];
    }
}
