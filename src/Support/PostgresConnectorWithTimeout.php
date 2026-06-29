<?php

namespace Laravel\Doctor\Support;

use Illuminate\Database\Connectors\PostgresConnector;

class PostgresConnectorWithTimeout extends PostgresConnector
{
    /**
     * Add the SSL options for the DSN.
     *
     * @param  array<string, mixed>  $config
     */
    protected function addSslOptions($dsn, array $config)
    {
        $dsn = parent::addSslOptions($dsn, $config);

        $timeout = $config['connect_timeout'] ?? null;

        if (is_int($timeout) || is_string($timeout)) {
            $dsn .= ';connect_timeout='.$timeout;
        }

        return $dsn;
    }
}
