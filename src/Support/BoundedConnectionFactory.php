<?php

namespace Laravel\Doctor\Support;

use Illuminate\Database\Connectors\ConnectionFactory;

class BoundedConnectionFactory extends ConnectionFactory
{
    /**
     * Create a connector instance based on the configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function createConnector(array $config)
    {
        if (($config['driver'] ?? null) === 'pgsql' && ! $this->container->bound('db.connector.pgsql')) {
            return new PostgresConnectorWithTimeout;
        }

        return parent::createConnector($config);
    }
}
