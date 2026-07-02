<?php

use Laravel\Doctor\Diagnostics\DefaultConfigurationEnvironmentVariablesAreDefined;
use Monolog\Handler\SyslogUdpHandler;

function doctor_configuration_environment_base_path(): string
{
    $basePath = sys_get_temp_dir().'/laravel-doctor-configuration-environment-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath.'/config', 0775, true);

    return $basePath;
}

it('reports missing laravel default config environment variables without defaults', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/app.php', <<<'PHP'
<?php

return [
    'required' => env('DOCTOR_REQUIRED_ENVIRONMENT_VALUE'),
    'optional' => env('DOCTOR_OPTIONAL_ENVIRONMENT_VALUE', 'fallback'),
];
PHP);

    $this->app->setBasePath($basePath);

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

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

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

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

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('pass');
});

it('ignores optional environment variables from laravel cache stores', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/cache.php', <<<'PHP'
<?php

return [
    'default' => env('CACHE_STORE', 'database'),
    'stores' => [
        'database' => [
            'driver' => 'database',
        ],
        'storage' => [
            'driver' => 'storage',
            'disk' => env('CACHE_STORAGE_DISK'),
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config(['cache.default' => 'database']);

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

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

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('pass');
});

it('ignores starter kit service credentials when the log mailer is selected', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/mail.php', <<<'PHP'
<?php

return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'log' => [
            'transport' => 'log',
        ],
        'postmark' => [
            'transport' => 'postmark',
        ],
        'resend' => [
            'transport' => 'resend',
        ],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],
];
PHP);

    file_put_contents($basePath.'/config/services.php', <<<'PHP'
<?php

return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config([
        'mail.default' => 'log',
        'mail.mailers.log.transport' => 'log',
        'mail.mailers.postmark.transport' => 'postmark',
        'mail.mailers.resend.transport' => 'resend',
    ]);

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

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

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

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

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('pass');
});

it('checks service credentials used by the selected laravel mailer', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/services.php', <<<'PHP'
<?php

return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],
    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('POSTMARK_API_KEY')
        ->and($result->details)->not->toContain('RESEND_API_KEY')
        ->and($result->details)->not->toContain('SLACK_BOT_USER_OAUTH_TOKEN');
});

it('ignores service credentials for unused laravel mailers', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/services.php', <<<'PHP'
<?php

return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config([
        'mail.default' => 'log',
        'mail.mailers.log.transport' => 'log',
        'mail.mailers.postmark.transport' => 'postmark',
        'mail.mailers.resend.transport' => 'resend',
    ]);

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('pass');
});

it('checks service credentials used by the selected laravel mailer transport', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/services.php', <<<'PHP'
<?php

return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config([
        'mail.default' => 'transactional',
        'mail.mailers.transactional.transport' => 'resend',
    ]);

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('RESEND_API_KEY')
        ->and($result->details)->not->toContain('POSTMARK_API_KEY');
});

it('resolves renamed broadcasting connections by driver', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/broadcasting.php', <<<'PHP'
<?php

return [
    'default' => env('BROADCAST_CONNECTION', 'null'),
    'connections' => [
        'websockets' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST'),
            ],
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config([
        'broadcasting.default' => 'websockets',
        'broadcasting.connections.websockets.driver' => 'pusher',
    ]);

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('PUSHER_APP_KEY')
        ->and($result->details)->toContain('PUSHER_APP_SECRET')
        ->and($result->details)->toContain('PUSHER_APP_ID')
        ->and($result->details)->not->toContain('PUSHER_APP_CLUSTER')
        ->and($result->details)->not->toContain('PUSHER_HOST');
});

it('resolves renamed filesystem disks by driver', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/filesystems.php', <<<'PHP'
<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'media' => [
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
    config([
        'filesystems.default' => 'media',
        'filesystems.disks.media.driver' => 's3',
    ]);

    // Testbench's skeleton environment defines the AWS variables this test asserts on.
    unset($_ENV['AWS_BUCKET'], $_SERVER['AWS_BUCKET'], $_ENV['AWS_DEFAULT_REGION'], $_SERVER['AWS_DEFAULT_REGION']);
    putenv('AWS_BUCKET');
    putenv('AWS_DEFAULT_REGION');

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('AWS_BUCKET')
        ->and($result->details)->toContain('AWS_DEFAULT_REGION')
        ->and($result->details)->not->toContain('AWS_ACCESS_KEY_ID')
        ->and($result->details)->not->toContain('AWS_URL');
});

it('resolves renamed slack log channels by driver', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/logging.php', <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'alerts' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config([
        'logging.default' => 'alerts',
        'logging.channels.alerts.driver' => 'slack',
    ]);

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('LOG_SLACK_WEBHOOK_URL');
});

it('resolves renamed papertrail log channels by handler', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/logging.php', <<<'PHP'
<?php

use Monolog\Handler\SyslogUdpHandler;

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'syslog-remote' => [
            'driver' => 'monolog',
            'handler' => SyslogUdpHandler::class,
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
        'logging.default' => 'syslog-remote',
        'logging.channels.syslog-remote.driver' => 'monolog',
        'logging.channels.syslog-remote.handler' => SyslogUdpHandler::class,
    ]);

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('PAPERTRAIL_URL')
        ->and($result->details)->toContain('PAPERTRAIL_PORT');
});

it('resolves renamed sqs queue connections by driver', function (): void {
    $basePath = doctor_configuration_environment_base_path();

    file_put_contents($basePath.'/config/queue.php', <<<'PHP'
<?php

return [
    'default' => env('QUEUE_CONNECTION', 'database'),
    'connections' => [
        'jobs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'suffix' => env('SQS_SUFFIX'),
            'overflow' => [
                'enabled' => env('SQS_OVERFLOW_ENABLED', false),
                'store' => env('SQS_OVERFLOW_STORE'),
            ],
        ],
    ],
];
PHP);

    $this->app->setBasePath($basePath);
    config([
        'queue.default' => 'jobs',
        'queue.connections.jobs.driver' => 'sqs',
        'queue.connections.jobs.overflow.enabled' => true,
    ]);

    $result = (new DefaultConfigurationEnvironmentVariablesAreDefined)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('SQS_OVERFLOW_STORE')
        ->and($result->details)->not->toContain('SQS_SUFFIX')
        ->and($result->details)->not->toContain('AWS_ACCESS_KEY_ID');
});
