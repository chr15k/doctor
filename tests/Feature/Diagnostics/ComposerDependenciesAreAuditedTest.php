<?php

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostics\ComposerDependenciesAreAudited;

function doctor_composer_audit_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-composer-audit-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0775, true);

    return $basePath;
}

it('reports Composer audit advisories', function (): void {
    $basePath = doctor_composer_audit_base_path();

    file_put_contents($basePath.'/composer.lock', '{}');

    $this->app->setBasePath($basePath);

    Process::fake([
        '*' => Process::result(output: json_encode([
            'advisories' => [
                'vendor/package' => [
                    ['advisoryId' => 'CVE-0000-0000'],
                ],
            ],
        ], JSON_THROW_ON_ERROR), exitCode: 1),
    ]);

    $result = (new ComposerDependenciesAreAudited)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->code)->toBe('composer-dependencies-are-audited.vulnerable')
        ->and($result->details)->toContain('1 security advisory');
});
