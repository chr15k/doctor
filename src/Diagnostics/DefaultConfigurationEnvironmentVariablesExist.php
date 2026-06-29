<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\File;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\EnvironmentVariables;

class DefaultConfigurationEnvironmentVariablesExist extends Diagnostic
{
    private const DEFAULT_CONFIGURATION_VARIABLE_RESOLVERS = [
        'app.php' => 'appConfigurationVariables',
        'auth.php' => 'authConfigurationVariables',
        'broadcasting.php' => 'broadcastingConfigurationVariables',
        'cache.php' => 'cacheConfigurationVariables',
        'concurrency.php' => 'concurrencyConfigurationVariables',
        'cors.php' => 'corsConfigurationVariables',
        'database.php' => 'databaseConfigurationVariables',
        'filesystems.php' => 'filesystemsConfigurationVariables',
        'hashing.php' => 'hashingConfigurationVariables',
        'logging.php' => 'loggingConfigurationVariables',
        'mail.php' => 'mailConfigurationVariables',
        'queue.php' => 'queueConfigurationVariables',
        'services.php' => 'servicesConfigurationVariables',
        'session.php' => 'sessionConfigurationVariables',
        'view.php' => 'viewConfigurationVariables',
    ];

    public string $name = 'Default configuration environment variables exist';

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

        $normalized = [];

        foreach ($variables as $variable => $files) {
            $normalized[$variable] = $files;
        }

        return $normalized;
    }

    /**
     * Get required environment variables for a configuration file.
     *
     * @return list<string>
     */
    private function requiredVariablesFor(string $file): array
    {
        $resolver = self::DEFAULT_CONFIGURATION_VARIABLE_RESOLVERS[basename($file)] ?? null;

        return $resolver === null ? [] : $this->{$resolver}($file);
    }

    /**
     * Get required environment variables from Laravel's app configuration.
     *
     * @return list<string>
     */
    private function appConfigurationVariables(string $file): array
    {
        return $this->exceptVariables($this->genericConfigurationVariables($file), [
            'ASSET_URL',
        ]);
    }

    /**
     * Get required environment variables from Laravel's auth configuration.
     *
     * @return list<string>
     */
    private function authConfigurationVariables(string $file): array
    {
        return $this->genericConfigurationVariables($file);
    }

    /**
     * Get required environment variables from Laravel's broadcasting configuration.
     *
     * @return list<string>
     */
    private function broadcastingConfigurationVariables(string $file): array
    {
        $variables = $this->genericConfigurationVariables($file);
        $driver = $this->configuredString('broadcasting.default', 'null');

        return $this->mergeVariables(
            $this->exceptVariables($variables, [
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
            ]),
            $this->selectedVariables($variables, match ($driver) {
                'ably' => ['ABLY_KEY'],
                'pusher' => ['PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET'],
                'reverb' => ['REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET'],
                default => [],
            }),
        );
    }

    /**
     * Get required environment variables from Laravel's cache configuration.
     *
     * @return list<string>
     */
    private function cacheConfigurationVariables(string $file): array
    {
        return $this->exceptVariables($this->genericConfigurationVariables($file), [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'DB_CACHE_CONNECTION',
            'DB_CACHE_LOCK_CONNECTION',
            'DB_CACHE_LOCK_TABLE',
            'DYNAMODB_ENDPOINT',
            'MEMCACHED_PASSWORD',
            'MEMCACHED_PERSISTENT_ID',
            'MEMCACHED_USERNAME',
        ]);
    }

    /**
     * Get required environment variables from Laravel's concurrency configuration.
     *
     * @return list<string>
     */
    private function concurrencyConfigurationVariables(string $file): array
    {
        return $this->genericConfigurationVariables($file);
    }

    /**
     * Get required environment variables from Laravel's CORS configuration.
     *
     * @return list<string>
     */
    private function corsConfigurationVariables(string $file): array
    {
        return $this->genericConfigurationVariables($file);
    }

    /**
     * Get required environment variables from Laravel's database configuration.
     *
     * @return list<string>
     */
    private function databaseConfigurationVariables(string $file): array
    {
        return $this->exceptVariables($this->genericConfigurationVariables($file), [
            'DB_URL',
            'MYSQL_ATTR_SSL_CA',
            'REDIS_PASSWORD',
            'REDIS_URL',
            'REDIS_USERNAME',
        ]);
    }

    /**
     * Get required environment variables from Laravel's filesystem configuration.
     *
     * @return list<string>
     */
    private function filesystemsConfigurationVariables(string $file): array
    {
        $variables = $this->genericConfigurationVariables($file);
        $disk = $this->configuredString('filesystems.default', 'local');

        return $this->mergeVariables(
            $this->exceptVariables($variables, [
                'APP_URL',
                'AWS_ACCESS_KEY_ID',
                'AWS_BUCKET',
                'AWS_DEFAULT_REGION',
                'AWS_ENDPOINT',
                'AWS_SECRET_ACCESS_KEY',
                'AWS_URL',
            ]),
            $this->selectedVariables($variables, $disk === 's3' ? [
                'AWS_BUCKET',
                'AWS_DEFAULT_REGION',
            ] : []),
        );
    }

    /**
     * Get required environment variables from Laravel's hashing configuration.
     *
     * @return list<string>
     */
    private function hashingConfigurationVariables(string $file): array
    {
        return $this->genericConfigurationVariables($file);
    }

    /**
     * Get required environment variables from Laravel's logging configuration.
     *
     * @return list<string>
     */
    private function loggingConfigurationVariables(string $file): array
    {
        $variables = $this->genericConfigurationVariables($file);
        $required = [];

        foreach ($this->activeLogChannels($this->configuredString('logging.default', 'stack')) as $channel) {
            $required = [
                ...$required,
                ...match ($channel) {
                    'papertrail' => ['PAPERTRAIL_PORT', 'PAPERTRAIL_URL'],
                    'slack' => ['LOG_SLACK_WEBHOOK_URL'],
                    default => [],
                },
            ];
        }

        return $this->mergeVariables(
            $this->exceptVariables($variables, [
                'LOG_SLACK_WEBHOOK_URL',
                'LOG_STDERR_FORMATTER',
                'PAPERTRAIL_PORT',
                'PAPERTRAIL_URL',
            ]),
            $this->selectedVariables($variables, $required),
        );
    }

    /**
     * Get required environment variables from Laravel's mail configuration.
     *
     * @return list<string>
     */
    private function mailConfigurationVariables(string $file): array
    {
        return $this->exceptVariables($this->genericConfigurationVariables($file), [
            'MAIL_LOG_CHANNEL',
            'MAIL_PASSWORD',
            'MAIL_SCHEME',
            'MAIL_URL',
            'MAIL_USERNAME',
        ]);
    }

    /**
     * Get required environment variables from Laravel's queue configuration.
     *
     * @return list<string>
     */
    private function queueConfigurationVariables(string $file): array
    {
        $variables = $this->genericConfigurationVariables($file);
        $required = [];

        if ($this->configuredString('queue.default', 'database') === 'sqs'
            && config('queue.connections.sqs.overflow.enabled') === true) {
            $required[] = 'SQS_OVERFLOW_STORE';
        }

        return $this->mergeVariables(
            $this->exceptVariables($variables, [
                'AWS_ACCESS_KEY_ID',
                'DB_QUEUE_CONNECTION',
                'SQS_OVERFLOW_STORE',
                'SQS_SUFFIX',
                'AWS_SECRET_ACCESS_KEY',
            ]),
            $this->selectedVariables($variables, $required),
        );
    }

    /**
     * Get required environment variables from Laravel's services configuration.
     *
     * @return list<string>
     */
    private function servicesConfigurationVariables(string $file): array
    {
        $variables = $this->genericConfigurationVariables($file);
        $required = [];

        foreach ($this->activeMailers($this->configuredString('mail.default', 'log')) as $mailer) {
            $required = [
                ...$required,
                ...match ($mailer) {
                    'postmark' => ['POSTMARK_TOKEN'],
                    'resend' => ['RESEND_KEY'],
                    default => [],
                },
            ];
        }

        return $this->mergeVariables(
            $this->exceptVariables($variables, [
                'AWS_ACCESS_KEY_ID',
                'AWS_SECRET_ACCESS_KEY',
                'GOOGLE_CLIENT_ID',
                'GOOGLE_CLIENT_SECRET',
                'POSTMARK_TOKEN',
                'RESEND_KEY',
                'SLACK_BOT_USER_DEFAULT_CHANNEL',
                'SLACK_BOT_USER_OAUTH_TOKEN',
            ]),
            $this->selectedVariables($variables, $required),
        );
    }

    /**
     * Get required environment variables from Laravel's session configuration.
     *
     * @return list<string>
     */
    private function sessionConfigurationVariables(string $file): array
    {
        return $this->exceptVariables($this->genericConfigurationVariables($file), [
            'SESSION_CONNECTION',
            'SESSION_DOMAIN',
            'SESSION_SECURE_COOKIE',
            'SESSION_STORE',
        ]);
    }

    /**
     * Get required environment variables from Laravel's view configuration.
     *
     * @return list<string>
     */
    private function viewConfigurationVariables(string $file): array
    {
        return $this->genericConfigurationVariables($file);
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
     * Get the concrete mailers used by a mailer.
     *
     * @param  list<string>  $seen
     * @return list<string>
     */
    private function activeMailers(string $mailer, array $seen = []): array
    {
        if (in_array($mailer, $seen, true)) {
            return [];
        }

        $transport = config("mail.mailers.{$mailer}.transport");

        if (! in_array($transport, ['failover', 'roundrobin'], true)) {
            return [$mailer];
        }

        $mailers = config("mail.mailers.{$mailer}.mailers");

        if (! is_array($mailers)) {
            return [];
        }

        $active = [];

        foreach ($mailers as $nested) {
            if (is_string($nested) && $nested !== '') {
                $active = [...$active, ...$this->activeMailers($nested, [...$seen, $mailer])];
            }
        }

        return array_values(array_unique($active));
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
        $files = File::glob(base_path('config/*.php')) ?: [];
        $configured = [];

        foreach ($files as $file) {
            if (is_string($file) && array_key_exists(basename($file), self::DEFAULT_CONFIGURATION_VARIABLE_RESOLVERS)) {
                $configured[] = $file;
            }
        }

        return $configured;
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
        return implode(PHP_EOL, array_map(
            static fn (string $variable, array $files): string => sprintf('- %s (%s)', $variable, implode(', ', array_unique($files))),
            array_keys($missing),
            $missing,
        ));
    }
}
