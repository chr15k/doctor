<?php

namespace Laravel\Doctor\Support;

final class ActiveDrivers
{
    /**
     * Get the concrete channels used by a logging channel.
     *
     * @param  list<string>  $seen
     * @return list<string>
     */
    public static function logChannels(string $channel, array $seen = []): array
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
                $active = [...$active, ...self::logChannels($nested, [...$seen, $channel])];
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
    public static function mailers(string $mailer, array $seen = []): array
    {
        if (in_array($mailer, $seen, true)) {
            return [];
        }

        $transport = Configured::string("mail.mailers.{$mailer}.transport", $mailer);

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
                $active = [...$active, ...self::mailers($nested, [...$seen, $mailer])];
            }
        }

        return array_values(array_unique($active));
    }
}
