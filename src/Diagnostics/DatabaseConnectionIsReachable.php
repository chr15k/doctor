<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\ConfigurationUrlParser;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use PDO;
use Throwable;

class DatabaseConnectionIsReachable extends Diagnostic
{
    private const CONNECTION_TIMEOUT_SECONDS = 2;

    public string $name = 'Database connects';

    public string $group = 'database';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-configured' => Outcome::skip('Laravel does not have a default database connection configured.'),
            'connection-missing' => Outcome::skip('The default database connection is not configured.'),
            'unreachable' => Outcome::fail(
                summary: 'Laravel cannot connect to the default database connection.',
                remediation: 'Check DB_CONNECTION and the database credentials in your environment file.',
            ),
            'reachable' => Outcome::pass('Laravel can connect to the default database connection.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $connection = config()->string('database.default', '');

        if ($connection === '') {
            return $this->result('not-configured');
        }

        $configuration = $this->configuration($connection);

        if ($configuration === null) {
            return $this->result('connection-missing')
                ->withDetails(sprintf('The [%s] database connection is not configured.', $connection));
        }

        try {
            $this->probe($connection, $configuration);
        } catch (Throwable $e) {
            return $this->result('unreachable')
                ->withDetails($e->getMessage());
        }

        return $this->result('reachable');
    }

    /**
     * Probe the database connection.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function probe(string $connection, array $configuration): void
    {
        $database = (new ConnectionFactory(app()))->make(
            $this->withConnectionTimeout($configuration),
            $connection,
        );

        try {
            $database->getPdo();
        } finally {
            $database->disconnect();
        }
    }

    /**
     * Get the default database connection configuration.
     *
     * @return array<string, mixed>|null
     */
    private function configuration(string $connection): ?array
    {
        $configuration = config("database.connections.{$connection}");

        if (! is_string($configuration) && ! is_array($configuration)) {
            return null;
        }

        return (new ConfigurationUrlParser)->parseConfiguration($configuration);
    }

    /**
     * Add conservative connection timeouts to the transient probe configuration.
     *
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function withConnectionTimeout(array $configuration): array
    {
        $options = $configuration['options'] ?? [];

        if (! is_array($options)) {
            $options = [];
        }

        $options[PDO::ATTR_TIMEOUT] ??= self::CONNECTION_TIMEOUT_SECONDS;

        $configuration['options'] = $options;

        if (($configuration['driver'] ?? null) === 'sqlsrv' && ! array_key_exists('login_timeout', $configuration)) {
            $configuration['login_timeout'] = self::CONNECTION_TIMEOUT_SECONDS;
        }

        return $configuration;
    }
}
