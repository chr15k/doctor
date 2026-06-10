<?php

use Laravel\Doctor\Diagnostics\VendorAutoloadExists;

function doctor_vendor_autoload_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-vendor-autoload-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('reports a missing Composer autoload file', function (): void {
    $basePath = doctor_vendor_autoload_base_path();
    file_put_contents($basePath.'/composer.json', '{}');

    $this->app->setBasePath($basePath);

    $result = (new VendorAutoloadExists)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->remediation[0])->toBe('Install Composer dependencies.');
});
