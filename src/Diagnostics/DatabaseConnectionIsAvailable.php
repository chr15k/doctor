<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\ConfigurationUrlParser;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\BoundedConnectionFactory;
use PDO;
use Throwable;

class DatabaseConnectionIsAvailable extends Diagnostic
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
            'manager-missing' => Outcome::skip('The database manager is not available.'),
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
        $connection = config('database.default');

        if (! is_string($connection) || $connection === '') {
            return $this->result('not-configured');
        }

        if (! app()->bound('db')) {
            return $this->result('manager-missing');
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
        $database = (new BoundedConnectionFactory(app()))->make(
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

        if (is_string($configuration)) {
            return (new ConfigurationUrlParser)->parseConfiguration($configuration);
        }

        if (! is_array($configuration)) {
            return null;
        }

        $normalized = [];

        foreach ($configuration as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return (new ConfigurationUrlParser)->parseConfiguration($normalized);
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

        if (($configuration['driver'] ?? null) === 'pgsql' && ! array_key_exists('connect_timeout', $configuration)) {
            $configuration['connect_timeout'] = self::CONNECTION_TIMEOUT_SECONDS;
        }

        if (($configuration['driver'] ?? null) === 'sqlsrv' && ! array_key_exists('login_timeout', $configuration)) {
            $configuration['login_timeout'] = self::CONNECTION_TIMEOUT_SECONDS;
        }

        return $configuration;
    }
}
