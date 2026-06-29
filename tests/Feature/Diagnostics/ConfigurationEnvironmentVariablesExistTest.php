<?php

use Laravel\Doctor\Diagnostics\ConfigurationEnvironmentVariablesExist;

function doctor_configuration_environment_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-configuration-environment-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath.'/config', 0775, true);

    return $basePath;
}

it('reports missing laravel config environment variables without defaults', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/app.php', <<<'PHP'
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

it('ignores package config environment variables', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/reverb.php', <<<'PHP'
<?php

return [
    'servers' => [
        'reverb' => [
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                ],
            ],
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);

    $result = (new ConfigurationEnvironmentVariablesExist)->check();

    expect($result->status->value)->toBe('pass');
});

it('ignores environment variables from unused laravel database connections', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/database.php', <<<'PHP'
<?php

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
        ],
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'options' => [
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ],
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config(['database.default' => 'sqlite']);

    $result = (new ConfigurationEnvironmentVariablesExist)->check();

    expect($result->status->value)->toBe('pass');
});

it('ignores environment variables from unused laravel filesystem disks', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/filesystems.php', <<<'PHP'
<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
        ],
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config(['filesystems.default' => 'local']);

    $result = (new ConfigurationEnvironmentVariablesExist)->check();

    expect($result->status->value)->toBe('pass');
});

it('checks only the active laravel broadcasting connection', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/broadcasting.php', <<<'PHP'
<?php

return [
    'default' => env('BROADCAST_CONNECTION', 'null'),
    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST'),
            ],
        ],
        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config(['broadcasting.default' => 'pusher']);

    $result = (new ConfigurationEnvironmentVariablesExist)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('PUSHER_APP_KEY')
        ->and($result->details)->toContain('PUSHER_APP_SECRET')
        ->and($result->details)->toContain('PUSHER_APP_ID')
        ->and($result->details)->not->toContain('PUSHER_APP_CLUSTER')
        ->and($result->details)->not->toContain('PUSHER_HOST')
        ->and($result->details)->not->toContain('ABLY_KEY');
});

it('checks only active laravel log channels', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/logging.php', <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],
        'single' => [
            'driver' => 'single',
        ],
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
        ],
        'papertrail' => [
            'driver' => 'monolog',
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config([
        'logging.default' => 'stack',
        'logging.channels.stack.driver' => 'stack',
        'logging.channels.stack.channels' => ['single'],
        'logging.channels.single.driver' => 'single',
    ]);

    $result = (new ConfigurationEnvironmentVariablesExist)->check();

    expect($result->status->value)->toBe('pass');
});

it('checks service credentials used by the selected laravel mailer', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/services.php', <<<'PHP'
<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'resend' => [
        'key' => env('RESEND_KEY'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config([
        'mail.default' => 'postmark',
        'mail.mailers.postmark.transport' => 'postmark',
    ]);

    $result = (new ConfigurationEnvironmentVariablesExist)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('POSTMARK_TOKEN')
        ->and($result->details)->not->toContain('RESEND_KEY')
        ->and($result->details)->not->toContain('SLACK_BOT_USER_OAUTH_TOKEN');
});
