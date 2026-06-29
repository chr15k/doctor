<?php

use Laravel\Doctor\Diagnostics\RequiredPhpExtensionsAreLoaded;

function doctor_required_php_extensions_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-required-php-extensions-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('reports missing Composer-required PHP extensions', function (): void {
    $basePath = doctor_required_php_extensions_base_path();
    file_put_contents($basePath.'/composer.json', json_encode([
        'require' => [
            'ext-laravel_doctor_missing_extension' => '*',
        ],
    ], JSON_THROW_ON_ERROR));

    $this->app->setBasePath($basePath);

    $result = (new RequiredPhpExtensionsAreLoaded)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('ext-laravel_doctor_missing_extension');
});
