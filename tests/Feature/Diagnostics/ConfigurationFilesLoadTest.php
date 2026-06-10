<?php

use Laravel\Doctor\Diagnostics\ConfigurationFilesLoad;

function doctor_configuration_files_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-configuration-files-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('reports configuration files that cannot load', function (): void {
    $basePath = doctor_configuration_files_base_path();
    mkdir($basePath.'/config');
    file_put_contents($basePath.'/config/broken.php', "<?php\n\nthrow new RuntimeException('broken config');\n");

    $this->app->setBasePath($basePath);

    $result = (new ConfigurationFilesLoad)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toBe('broken.php: broken config');
});
