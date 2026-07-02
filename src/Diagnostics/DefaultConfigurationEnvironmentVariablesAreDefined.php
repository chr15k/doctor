<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\File;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\Details;
use Laravel\Doctor\Support\EnvironmentVariables;
use Monolog\Handler\SyslogUdpHandler;

class DefaultConfigurationEnvironmentVariablesAreDefined extends Diagnostic
{
    private const DEFAULT_CONFIGURATION_FILES = [
        'app.php',
        'auth.php',
        'broadcasting.php',
        'cache.php',
        'concurrency.php',
        'cors.php',
        'database.php',
        'filesystems.php',
        'hashing.php',
        'logging.php',
        'mail.php',
        'queue.php',
        'services.php',
        'session.php',
        'view.php',
    ];

    /**
     * Environment variables that may be left undefined per configuration file.
     *
     * These are variables the framework tolerates as null, or credentials the
     * platform may provide out of band (for example AWS credentials resolved
     * from IAM roles). Variables that become required once a specific driver
     * is active are re-added by conditionallyRequiredVariables().
     */
    private const OPTIONAL_VARIABLES = [
        'app.php' => [
            'ASSET_URL',
        ],
        'broadcasting.php' => [
            'ABLY_KEY',
            'PUSHER_APP_CLUSTER',
            'PUSHER_APP_ID',
            'PUSHER_APP_KEY',
            'PUSHER_APP_SECRET',
            'PUSHER_HOST',
            'REVERB_APP_ID',
            'REVERB_APP_KEY',
            'REVERB_APP_SECRET',
            'REVERB_HOST',
        ],
        'cache.php' => [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'CACHE_STORAGE_DISK',
            'DB_CACHE_CONNECTION',
            'DB_CACHE_LOCK_CONNECTION',
            'DB_CACHE_LOCK_TABLE',
            'DYNAMODB_ENDPOINT',
            'MEMCACHED_PASSWORD',
            'MEMCACHED_PERSISTENT_ID',
            'MEMCACHED_USERNAME',
        ],
        'database.php' => [
            'DB_URL',
            'MYSQL_ATTR_SSL_CA',
            'REDIS_PASSWORD',
            'REDIS_URL',
            'REDIS_USERNAME',
        ],
        'filesystems.php' => [
            'APP_URL',
            'AWS_ACCESS_KEY_ID',
            'AWS_BUCKET',
            'AWS_DEFAULT_REGION',
            'AWS_ENDPOINT',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_URL',
        ],
        'logging.php' => [
            'LOG_SLACK_WEBHOOK_URL',
            'LOG_STDERR_FORMATTER',
            'PAPERTRAIL_PORT',
            'PAPERTRAIL_URL',
        ],
        'mail.php' => [
            'MAIL_LOG_CHANNEL',
            'MAIL_PASSWORD',
            'MAIL_SCHEME',
            'MAIL_URL',
            'MAIL_USERNAME',
            'POSTMARK_API_KEY',
            'POSTMARK_TOKEN',
            'RESEND_API_KEY',
            'RESEND_KEY',
        ],
        'queue.php' => [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'DB_QUEUE_CONNECTION',
            'SQS_OVERFLOW_STORE',
            'SQS_SUFFIX',
        ],
        'services.php' => [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'GOOGLE_CLIENT_ID',
            'GOOGLE_CLIENT_SECRET',
            'POSTMARK_API_KEY',
            'POSTMARK_TOKEN',
            'RESEND_API_KEY',
            'RESEND_KEY',
            'SLACK_BOT_USER_DEFAULT_CHANNEL',
            'SLACK_BOT_USER_OAUTH_TOKEN',
        ],
        'session.php' => [
            'SESSION_CONNECTION',
            'SESSION_DOMAIN',
            'SESSION_SECURE_COOKIE',
            'SESSION_STORE',
        ],
    ];

    public string $name = 'Config env vars are defined';

    public string $group = 'configuration';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'defined' => Outcome::pass('Every required environment variable referenced by Laravel default configuration files is defined.'),
            'missing' => Outcome::fail(
                summary: 'Some Laravel default configuration environment variables are missing.',
                remediation: 'Define the missing variables in the application environment or add safe defaults to Laravel default configuration files.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $missing = $this->missingVariables();

        if ($missing === []) {
            return $this->result('defined');
        }

        return $this->result('missing')
            ->withDetails($this->formatMissingVariables($missing));
    }

    /**
     * Get missing required environment variables referenced from Laravel default config files.
     *
     * @return array<string, list<string>>
     */
    private function missingVariables(): array
    {
        $missing = [];
        $environment = new EnvironmentVariables;

        foreach ($this->requiredVariables() as $variable => $files) {
            if (! $environment->has($variable)) {
                $missing[$variable] = $files;
            }
        }

        return $missing;
    }

    /**
     * Get required environment variables referenced from Laravel default config files.
     *
     * @return array<string, list<string>>
     */
    private function requiredVariables(): array
    {
        $variables = [];

        foreach ($this->configurationFiles() as $file) {
            foreach ($this->requiredVariablesFor($file) as $variable) {
                $variables[$variable][] = basename($file);
            }
        }

        return $variables;
    }

    /**
     * Get required environment variables for a configuration file.
     *
     * @return list<string>
     */
    private function requiredVariablesFor(string $file): array
    {
        $variables = $this->genericConfigurationVariables($file);
        $name = basename($file);

        return $this->mergeVariables(
            $this->exceptVariables($variables, self::OPTIONAL_VARIABLES[$name] ?? []),
            $this->selectedVariables($variables, $this->conditionallyRequiredVariables($name)),
        );
    }

    /**
     * Get variables required by the active drivers for a configuration file.
     *
     * @return list<string>
     */
    private function conditionallyRequiredVariables(string $file): array
    {
        return match ($file) {
            'broadcasting.php' => $this->requiredBroadcastingVariables(),
            'filesystems.php' => $this->requiredFilesystemsVariables(),
            'logging.php' => $this->requiredLoggingVariables(),
            'mail.php', 'services.php' => $this->requiredMailCredentialVariables(),
            'queue.php' => $this->requiredQueueVariables(),
            default => [],
        };
    }

    /**
     * Get variables required by the default broadcasting connection.
     *
     * @return list<string>
     */
    private function requiredBroadcastingVariables(): array
    {
        return match ($this->activeDriver('broadcasting.connections', 'broadcasting.default', 'null')) {
            'ably' => ['ABLY_KEY'],
            'pusher' => ['PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET'],
            'reverb' => ['REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET'],
            default => [],
        };
    }

    /**
     * Get variables required by the default filesystem disk.
     *
     * @return list<string>
     */
    private function requiredFilesystemsVariables(): array
    {
        return $this->activeDriver('filesystems.disks', 'filesystems.default', 'local') === 's3'
            ? ['AWS_BUCKET', 'AWS_DEFAULT_REGION']
            : [];
    }

    /**
     * Get variables required by the active log channels.
     *
     * @return list<string>
     */
    private function requiredLoggingVariables(): array
    {
        $required = [];

        foreach ($this->activeLogChannels($this->configuredString('logging.default', 'stack')) as $channel) {
            $required = [...$required, ...$this->logChannelVariables($channel)];
        }

        return $this->mergeVariables($required);
    }

    /**
     * Get variables required by a log channel.
     *
     * @return list<string>
     */
    private function logChannelVariables(string $channel): array
    {
        $driver = $this->configuredString("logging.channels.{$channel}.driver", $channel);

        if ($driver === 'slack') {
            return ['LOG_SLACK_WEBHOOK_URL'];
        }

        if ($driver === 'monolog' && config("logging.channels.{$channel}.handler") === SyslogUdpHandler::class) {
            return ['PAPERTRAIL_PORT', 'PAPERTRAIL_URL'];
        }

        return [];
    }

    /**
     * Get variables required by the default queue connection.
     *
     * @return list<string>
     */
    private function requiredQueueVariables(): array
    {
        $connection = $this->configuredString('queue.default', 'database');

        if ($this->configuredString("queue.connections.{$connection}.driver", $connection) !== 'sqs') {
            return [];
        }

        return config("queue.connections.{$connection}.overflow.enabled") === true
            ? ['SQS_OVERFLOW_STORE']
            : [];
    }

    /**
     * Get service credential variables required by the default mailer.
     *
     * @return list<string>
     */
    private function requiredMailCredentialVariables(): array
    {
        $required = [];

        foreach ($this->activeMailTransports($this->configuredString('mail.default', 'log')) as $transport) {
            $required = [
                ...$required,
                ...match ($transport) {
                    'postmark' => ['POSTMARK_API_KEY', 'POSTMARK_TOKEN'],
                    'resend' => ['RESEND_API_KEY', 'RESEND_KEY'],
                    default => [],
                },
            ];
        }

        return $this->mergeVariables($required);
    }

    /**
     * Get every env() variable without an explicit default from a configuration file.
     *
     * @return list<string>
     */
    private function genericConfigurationVariables(string $file): array
    {
        $variables = [];

        foreach ($this->envCalls($file) as $call) {
            if (! $call['hasDefault']) {
                $variables[] = $call['name'];
            }
        }

        return array_values(array_unique($variables));
    }

    /**
     * Resolve the driver of the configured default connection, falling back to its name.
     */
    private function activeDriver(string $connections, string $default, string $fallback): string
    {
        $name = $this->configuredString($default, $fallback);

        return $this->configuredString("{$connections}.{$name}.driver", $name);
    }

    /**
     * Get the concrete channels used by a logging channel.
     *
     * @param  list<string>  $seen
     * @return list<string>
     */
    private function activeLogChannels(string $channel, array $seen = []): array
    {
        if (in_array($channel, $seen, true)) {
            return [];
        }

        $driver = config("logging.channels.{$channel}.driver");

        if ($driver !== 'stack') {
            return [$channel];
        }

        $channels = config("logging.channels.{$channel}.channels");

        if (! is_array($channels)) {
            return [];
        }

        $active = [];

        foreach ($channels as $nested) {
            if (is_string($nested) && $nested !== '') {
                $active = [...$active, ...$this->activeLogChannels($nested, [...$seen, $channel])];
            }
        }

        return array_values(array_unique($active));
    }

    /**
     * Get the transports used by a mailer.
     *
     * @param  list<string>  $seen
     * @return list<string>
     */
    private function activeMailTransports(string $mailer, array $seen = []): array
    {
        if (in_array($mailer, $seen, true)) {
            return [];
        }

        $transport = $this->configuredString("mail.mailers.{$mailer}.transport", $mailer);

        if (! in_array($transport, ['failover', 'roundrobin'], true)) {
            return [$transport];
        }

        $mailers = config("mail.mailers.{$mailer}.mailers");

        if (! is_array($mailers)) {
            return [];
        }

        $transports = [];

        foreach ($mailers as $nested) {
            if (is_string($nested) && $nested !== '') {
                $transports = [...$transports, ...$this->activeMailTransports($nested, [...$seen, $mailer])];
            }
        }

        return array_values(array_unique($transports));
    }

    /**
     * Get a string configuration value with a fallback.
     */
    private function configuredString(string $key, string $default): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Remove variables from a list.
     *
     * @param  list<string>  $variables
     * @param  list<string>  $excluded
     * @return list<string>
     */
    private function exceptVariables(array $variables, array $excluded): array
    {
        return array_values(array_diff($variables, $excluded));
    }

    /**
     * Select variables from a list.
     *
     * @param  list<string>  $variables
     * @param  list<string>  $selected
     * @return list<string>
     */
    private function selectedVariables(array $variables, array $selected): array
    {
        return array_values(array_intersect($variables, $selected));
    }

    /**
     * Merge variable lists.
     *
     * @param  list<string>  ...$variables
     * @return list<string>
     */
    private function mergeVariables(array ...$variables): array
    {
        return array_values(array_unique(array_merge(...$variables)));
    }

    /**
     * Get Laravel default configuration files.
     *
     * @return list<string>
     */
    private function configurationFiles(): array
    {
        return array_values(array_filter(
            File::glob(base_path('config/*.php')) ?: [],
            static fn (string $file): bool => in_array(basename($file), self::DEFAULT_CONFIGURATION_FILES, true),
        ));
    }

    /**
     * Get env() calls from a configuration file.
     *
     * @return list<array{name: string, hasDefault: bool}>
     */
    private function envCalls(string $file): array
    {
        $calls = [];
        $tokens = token_get_all(File::get($file));

        foreach ($tokens as $index => $token) {
            if (! $this->isEnvFunctionToken($token)) {
                continue;
            }

            $call = $this->parseEnvCall($tokens, $index);

            if ($call !== null) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * Determine whether a token is the env helper name.
     */
    private function isEnvFunctionToken(mixed $token): bool
    {
        return is_array($token)
            && $token[0] === T_STRING
            && is_string($token[1] ?? null)
            && strtolower($token[1]) === 'env';
    }

    /**
     * Parse an env() call.
     *
     * @param  array<int, mixed>  $tokens
     * @return array{name: string, hasDefault: bool}|null
     */
    private function parseEnvCall(array $tokens, int $index): ?array
    {
        $open = $this->nextNonWhitespaceTokenIndex($tokens, $index + 1);

        if ($open === null || $this->tokenText($tokens[$open]) !== '(') {
            return null;
        }

        $name = $this->firstArgument($tokens, $open);

        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'hasDefault' => $this->hasSecondArgument($tokens, $open),
        ];
    }

    /**
     * Get the first string argument from an env() call.
     *
     * @param  array<int, mixed>  $tokens
     */
    private function firstArgument(array $tokens, int $open): ?string
    {
        $index = $this->nextNonWhitespaceTokenIndex($tokens, $open + 1);

        return $index === null ? null : $this->quotedStringValue($tokens[$index]);
    }

    /**
     * Determine whether an env() call has a second argument.
     *
     * @param  array<int, mixed>  $tokens
     */
    private function hasSecondArgument(array $tokens, int $open): bool
    {
        $depth = 0;

        for ($index = $open; $index < count($tokens); $index++) {
            $text = $this->tokenText($tokens[$index]);

            if ($text === '(') {
                $depth++;
            }

            if ($text === ')') {
                $depth--;

                if ($depth === 0) {
                    return false;
                }
            }

            if ($text === ',' && $depth === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the next non-whitespace token index.
     *
     * @param  array<int, mixed>  $tokens
     */
    private function nextNonWhitespaceTokenIndex(array $tokens, int $start): ?int
    {
        for ($index = $start; $index < count($tokens); $index++) {
            if (! $this->isWhitespaceToken($tokens[$index])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Determine whether the token is whitespace.
     */
    private function isWhitespaceToken(mixed $token): bool
    {
        return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }

    /**
     * Get the value from a quoted string token.
     */
    private function quotedStringValue(mixed $token): ?string
    {
        if (! is_array($token)
            || $token[0] !== T_CONSTANT_ENCAPSED_STRING
            || ! is_string($token[1] ?? null)) {
            return null;
        }

        return stripcslashes(substr($token[1], 1, -1));
    }

    /**
     * Get a token's source text.
     */
    private function tokenText(mixed $token): string
    {
        if (is_string($token)) {
            return $token;
        }

        return is_array($token) && is_string($token[1] ?? null) ? $token[1] : '';
    }

    /**
     * Format missing variables.
     *
     * @param  array<string, list<string>>  $missing
     */
    private function formatMissingVariables(array $missing): string
    {
        return Details::bullets(array_map(
            static fn (string $variable, array $files): string => sprintf('%s (%s)', $variable, implode(', ', array_unique($files))),
            array_keys($missing),
            $missing,
        ));
    }
}
