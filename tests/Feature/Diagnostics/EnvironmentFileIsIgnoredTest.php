<?php

use Laravel\Doctor\Diagnostics\EnvironmentFileIsIgnored;

function doctor_environment_ignored_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-environment-ignored-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('passes when environment files are gitignored', function (): void {
    $basePath = doctor_environment_ignored_base_path();

    file_put_contents($basePath.'/.gitignore', ".env*\n");

    $this->app->setBasePath($basePath);

    $result = (new EnvironmentFileIsIgnored)->check();

    expect($result->status->value)->toBe('pass');
});
