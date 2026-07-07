<?php

use Laravel\Doctor\Diagnostics\PhpVersionSatisfiesComposerRequirement;

function doctor_php_version_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-php-version-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('reports when PHP does not satisfy composer.json', function (): void {
    $basePath = doctor_php_version_base_path();
    file_put_contents($basePath.'/composer.json', json_encode([
        'require' => [
            'php' => '<1.0',
        ],
    ], JSON_THROW_ON_ERROR));

    $this->app->setBasePath($basePath);

    $result = $this->app->make(PhpVersionSatisfiesComposerRequirement::class)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->summary)->toBe(sprintf('PHP %s does not satisfy [<1.0].', PHP_VERSION))
        ->and($result->details)->toBeNull();
});
