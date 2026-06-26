<?php

use Laravel\Doctor\Diagnostics\ConfigurationEnvironmentVariablesExist;

function doctor_configuration_environment_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-configuration-environment-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath.'/config', 0775, true);

    return $basePath;
}

it('reports missing config environment variables without defaults', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/doctor.php', <<<'PHP'
<?php

return [
    'required' => env('DOCTOR_REQUIRED_ENVIRONMENT_VALUE'),
    'optional' => env('DOCTOR_OPTIONAL_ENVIRONMENT_VALUE', 'fallback'),
];
PHP);

    $this->app->setBasePath($basePath);

    $result = (new ConfigurationEnvironmentVariablesExist)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('DOCTOR_REQUIRED_ENVIRONMENT_VALUE')
        ->and($result->details)->not->toContain('DOCTOR_OPTIONAL_ENVIRONMENT_VALUE');
});
