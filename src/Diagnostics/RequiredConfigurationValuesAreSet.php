<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\Details;
use Monolog\Handler\SyslogUdpHandler;

class RequiredConfigurationValuesAreSet extends Diagnostic
{
    public string $name = 'Config values are set';

    public string $group = 'configuration';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'set' => Outcome::pass('Every configuration value required by the active drivers is set.'),
            'missing' => Outcome::fail(
                summary: 'Some configuration values required by the active drivers are not set.',
                remediation: 'Set the missing configuration values, typically by defining their environment variables in the application environment.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $missing = $this->missingValues();

        if ($missing === []) {
            return $this->result('set');
        }

        return $this->result('missing')
            ->withDetails($this->formatMissingValues($missing));
    }

    /**
     * Get required configuration keys without a value, mapped to the feature that needs them.
     *
     * @return array<string, string>
     */
    private function missingValues(): array
    {
        return array_filter(
            $this->requiredValues(),
            fn (string $key): bool => ! $this->hasValue($key),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Get configuration keys required by the active drivers, mapped to the feature that needs them.
     *
     * @return array<string, string>
     */
    private function requiredValues(): array
    {
        return [
            ...$this->broadcastingValues(),
            ...$this->filesystemValues(),
            ...$this->loggingValues(),
            ...$this->mailValues(),
            ...$this->queueValues(),
        ];
    }

    /**
     * Get configuration keys required by the default broadcasting connection.
     *
     * @return array<string, string>
     */
    private function broadcastingValues(): array
    {
        $connection = $this->configuredString('broadcasting.default', 'null');
        $driver = $this->configuredString("broadcasting.connections.{$connection}.driver", $connection);

        $keys = match ($driver) {
            'ably' => ['key'],
            'pusher', 'reverb' => ['app_id', 'key', 'secret'],
            default => [],
        };

        return $this->values("broadcasting.connections.{$connection}", $keys, "{$driver} broadcaster");
    }

    /**
     * Get configuration keys required by the default filesystem disk.
     *
     * @return array<string, string>
     */
    private function filesystemValues(): array
    {
        $disk = $this->configuredString('filesystems.default', 'local');

        if ($this->configuredString("filesystems.disks.{$disk}.driver", $disk) !== 's3') {
            return [];
        }

        return $this->values("filesystems.disks.{$disk}", ['bucket', 'region'], 's3 filesystem disk');
    }

    /**
     * Get configuration keys required by the active log channels.
     *
     * @return array<string, string>
     */
    private function loggingValues(): array
    {
        $values = [];

        foreach ($this->activeLogChannels($this->configuredString('logging.default', 'stack')) as $channel) {
            $values = [...$values, ...$this->logChannelValues($channel)];
        }

        return $values;
    }

    /**
     * Get configuration keys required by a log channel.
     *
     * @return array<string, string>
     */
    private function logChannelValues(string $channel): array
    {
        $driver = $this->configuredString("logging.channels.{$channel}.driver", $channel);

        if ($driver === 'slack') {
            return $this->values("logging.channels.{$channel}", ['url'], 'slack log channel');
        }

        if ($driver === 'monolog' && config("logging.channels.{$channel}.handler") === SyslogUdpHandler::class) {
            return $this->values("logging.channels.{$channel}.handler_with", ['host', 'port'], "{$channel} log channel");
        }

        return [];
    }

    /**
     * Get configuration keys required by the default mailer.
     *
     * @return array<string, string>
     */
    private function mailValues(): array
    {
        $values = [];

        foreach ($this->activeMailers($this->configuredString('mail.default', 'log')) as $mailer) {
            $values = [...$values, ...$this->mailerValues($mailer)];
        }

        return $values;
    }

    /**
     * Get configuration keys required by a mailer.
     *
     * @return array<string, string>
     */
    private function mailerValues(string $mailer): array
    {
        return match ($this->configuredString("mail.mailers.{$mailer}.transport", $mailer)) {
            'smtp' => $this->values("mail.mailers.{$mailer}", ['host'], 'smtp mailer'),
            'sendmail' => $this->values("mail.mailers.{$mailer}", ['path'], 'sendmail mailer'),
            'postmark' => $this->values('services.postmark', ['token'], 'postmark mailer'),
            'resend' => $this->values('services.resend', ['key'], 'resend mailer'),
            default => [],
        };
    }

    /**
     * Get configuration keys required by the default queue connection.
     *
     * @return array<string, string>
     */
    private function queueValues(): array
    {
        $connection = $this->configuredString('queue.default', 'database');

        if ($this->configuredString("queue.connections.{$connection}.driver", $connection) !== 'sqs') {
            return [];
        }

        if (config("queue.connections.{$connection}.overflow.enabled") !== true) {
            return [];
        }

        return $this->values("queue.connections.{$connection}.overflow", ['store'], 'sqs queue overflow');
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

        if (config("logging.channels.{$channel}.driver") !== 'stack') {
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

        $transport = $this->configuredString("mail.mailers.{$mailer}.transport", $mailer);

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
     * Map configuration keys under a prefix to the feature that requires them.
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function values(string $prefix, array $keys, string $feature): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values["{$prefix}.{$key}"] = $feature;
        }

        return $values;
    }

    /**
     * Determine whether a configuration key holds a usable value.
     */
    private function hasValue(string $key): bool
    {
        $value = config($key);

        return match (true) {
            $value === null => false,
            is_string($value) => trim($value) !== '',
            is_array($value) => $value !== [],
            default => true,
        };
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
     * Format missing configuration values.
     *
     * @param  array<string, string>  $missing
     */
    private function formatMissingValues(array $missing): string
    {
        return Details::bullets(array_map(
            static fn (string $key, string $feature): string => sprintf('%s (%s)', $key, $feature),
            array_keys($missing),
            $missing,
        ));
    }
}
