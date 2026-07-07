<?php

use Laravel\Doctor\Diagnostics\RecommendedPhpExtensionsAreLoaded;

function doctor_recommended_php_extensions_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-recommended-php-extensions-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('warns about missing Composer-suggested PHP extensions', function (): void {
    $basePath = doctor_recommended_php_extensions_base_path();
    file_put_contents($basePath.'/composer.json', json_encode([
        'suggest' => [
            'ext-laravel_doctor_recommended_extension' => 'Used by optional Doctor tests.',
        ],
    ], JSON_THROW_ON_ERROR));

    $this->app->setBasePath($basePath);

    $result = $this->app->make(RecommendedPhpExtensionsAreLoaded::class)->check();

    expect($result->status->value)->toBe('warn')
        ->and($result->details)->toContain('ext-laravel_doctor_recommended_extension');
});
