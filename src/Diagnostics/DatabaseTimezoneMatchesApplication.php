<?php

namespace Laravel\Doctor\Diagnostics;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Throwable;

/**
 * @phpstan-type ComparableTimezone array{name: string, offset: int|null}
 */
class DatabaseTimezoneMatchesApplication extends Diagnostic
{
    public string $name = 'Database timezone matches application';

    public string $group = 'database';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'application-timezone-invalid' => Outcome::skip('Laravel does not have a valid application timezone configured.'),
            'connection-missing' => Outcome::skip('Laravel does not have a default database connection configured.'),
            'manager-missing' => Outcome::skip('The database manager is not available.'),
            'connection-inspection-failed' => Outcome::warn(
                summary: 'Laravel could not inspect the database timezone.',
                remediation: 'Confirm the default database connection is reachable, then run Doctor again.',
            ),
            'sqlite' => Outcome::skip('SQLite does not expose a database timezone.'),
            'unsupported-driver' => Outcome::skip('The [{driver}] database driver does not expose a comparable timezone.'),
            'timezone-inspection-failed' => Outcome::warn(
                summary: 'Laravel could not inspect the database timezone.',
                remediation: 'Confirm the database user may read the session timezone, then run Doctor again.',
            ),
            'matches' => Outcome::pass('The default database connection uses the application timezone.'),
            'mismatch' => Outcome::warn(
                summary: 'The database timezone does not match the application timezone.',
                remediation: 'Set the database session timezone to match app.timezone, or intentionally keep both at UTC.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $applicationTimezone = $this->applicationTimezone();

        if (! $applicationTimezone instanceof DateTimeZone) {
            return $this->result('application-timezone-invalid');
        }

        $connection = $this->configuredConnection();

        if ($connection === null) {
            return $this->result('connection-missing');
        }

        if (! app()->bound('db')) {
            return $this->result('manager-missing');
        }

        try {
            $database = $this->database()->connection($connection);
            $driver = $database->getDriverName();
        } catch (Throwable $e) {
            return $this->result('connection-inspection-failed')
                ->withDetails($e->getMessage());
        }

        if ($driver === 'sqlite') {
            return $this->result('sqlite');
        }

        if (! $this->supportsDriver($driver)) {
            return $this->result('unsupported-driver', ['driver' => $driver]);
        }

        try {
            $databaseTimezone = $this->databaseTimezone($database, $driver);
        } catch (Throwable $e) {
            return $this->result('timezone-inspection-failed')
                ->withDetails($e->getMessage());
        }

        if ($this->timezonesMatch($applicationTimezone, $databaseTimezone)) {
            return $this->result('matches');
        }

        return $this->result('mismatch')
            ->withDetails($this->formatMismatch($applicationTimezone, $databaseTimezone, $connection, $driver));
    }

    /**
     * Get the configured application timezone.
     */
    private function applicationTimezone(): ?DateTimeZone
    {
        $timezone = config('app.timezone');

        if (! is_string($timezone) || $timezone === '') {
            return null;
        }

        return $this->timezone($timezone);
    }

    /**
     * Get the configured default database connection.
     */
    private function configuredConnection(): ?string
    {
        $connection = config('database.default');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    /**
     * Get the database manager.
     */
    private function database(): DatabaseManager
    {
        /** @var DatabaseManager $database */
        $database = app('db');

        return $database;
    }

    /**
     * Determine whether the database driver has a comparable timezone.
     */
    private function supportsDriver(string $driver): bool
    {
        return in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true);
    }

    /**
     * Get the database timezone for the given driver.
     *
     * @return ComparableTimezone
     */
    private function databaseTimezone(Connection $connection, string $driver): array
    {
        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlTimezone($connection),
            'pgsql' => $this->postgresTimezone($connection),
            'sqlsrv' => $this->sqlServerTimezone($connection),
            default => ['name' => $driver, 'offset' => null],
        };
    }

    /**
     * Get the MySQL or MariaDB session timezone.
     *
     * @return ComparableTimezone
     */
    private function mysqlTimezone(Connection $connection): array
    {
        $row = $connection->selectOne(
            'select @@session.time_zone as time_zone, timestampdiff(second, utc_timestamp(), now()) as offset_seconds'
        );

        $timezone = $this->stringValue($row, 'time_zone') ?? 'SYSTEM';
        $offset = $this->integerValue($row, 'offset_seconds')
            ?? $this->offsetFromString($timezone)
            ?? $this->offsetFromTimezoneName($timezone);

        return ['name' => $timezone, 'offset' => $offset];
    }

    /**
     * Get the PostgreSQL session timezone.
     *
     * @return ComparableTimezone
     */
    private function postgresTimezone(Connection $connection): array
    {
        $timezone = $connection->scalar("select current_setting('TIMEZONE')");

        if (! is_string($timezone) || $timezone === '') {
            $timezone = 'unknown';
        }

        return [
            'name' => $timezone,
            'offset' => $this->offsetFromTimezoneName($timezone),
        ];
    }

    /**
     * Get the SQL Server local offset.
     *
     * @return ComparableTimezone
     */
    private function sqlServerTimezone(Connection $connection): array
    {
        $offset = $connection->scalar('select datediff(second, sysutcdatetime(), sysdatetime()) as offset_seconds');

        return [
            'name' => 'current SQL Server offset',
            'offset' => $this->integerValue($offset),
        ];
    }

    /**
     * Determine whether the application and database timezones are equivalent.
     *
     * @param  ComparableTimezone  $databaseTimezone
     */
    private function timezonesMatch(DateTimeZone $applicationTimezone, array $databaseTimezone): bool
    {
        $timezone = $this->timezone($databaseTimezone['name']);

        if ($timezone instanceof DateTimeZone) {
            return $this->offsetsMatch($applicationTimezone, $timezone);
        }

        return $databaseTimezone['offset'] !== null
            && $this->currentOffset($applicationTimezone) === $databaseTimezone['offset'];
    }

    /**
     * Determine whether two timezones share the same offsets across common dates.
     */
    private function offsetsMatch(DateTimeZone $first, DateTimeZone $second): bool
    {
        foreach ($this->comparisonDates() as $date) {
            if ($date->setTimezone($first)->getOffset() !== $date->setTimezone($second)->getOffset()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the dates used to compare timezone offsets.
     *
     * @return list<DateTimeImmutable>
     */
    private function comparisonDates(): array
    {
        $timezone = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $timezone);
        $year = $now->format('Y');

        return [
            $now,
            new DateTimeImmutable("{$year}-01-01 12:00:00", $timezone),
            new DateTimeImmutable("{$year}-07-01 12:00:00", $timezone),
        ];
    }

    /**
     * Resolve a timezone name into a PHP timezone.
     */
    private function timezone(string $timezone): ?DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the current offset for a timezone.
     */
    private function currentOffset(DateTimeZone $timezone): int
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone($timezone)
            ->getOffset();
    }

    /**
     * Get the current offset for a timezone name.
     */
    private function offsetFromTimezoneName(string $timezone): ?int
    {
        $timezone = $this->timezone($timezone);

        return $timezone instanceof DateTimeZone ? $this->currentOffset($timezone) : null;
    }

    /**
     * Parse a timezone offset string into seconds.
     */
    private function offsetFromString(string $timezone): ?int
    {
        if (! preg_match('/^([+-])(\d{2}):(\d{2})$/', $timezone, $matches)) {
            return null;
        }

        $seconds = (((int) $matches[2]) * 60 * 60) + (((int) $matches[3]) * 60);

        return $matches[1] === '-' ? -$seconds : $seconds;
    }

    /**
     * Get a string value from a query result.
     */
    private function stringValue(mixed $record, string $key): ?string
    {
        $value = $this->value($record, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Get an integer value from a query result.
     */
    private function integerValue(mixed $record, ?string $key = null): ?int
    {
        $value = $key === null ? $record : $this->value($record, $key);

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return is_string($value) && is_numeric($value) ? (int) $value : null;
    }

    /**
     * Get a value from an object or array result.
     */
    private function value(mixed $record, string $key): mixed
    {
        if (! is_array($record) && ! is_object($record)) {
            return null;
        }

        return ((array) $record)[$key] ?? null;
    }

    /**
     * Format the mismatch details.
     *
     * @param  ComparableTimezone  $databaseTimezone
     */
    private function formatMismatch(
        DateTimeZone $applicationTimezone,
        array $databaseTimezone,
        string $connection,
        string $driver,
    ): string {
        return implode(PHP_EOL, [
            sprintf('Application timezone: %s', $this->formatApplicationTimezone($applicationTimezone)),
            sprintf('Database timezone: %s', $this->formatDatabaseTimezone($databaseTimezone)),
            sprintf('Connection: %s (%s)', $connection, $driver),
        ]);
    }

    /**
     * Format an application timezone.
     */
    private function formatApplicationTimezone(DateTimeZone $timezone): string
    {
        return sprintf('%s (%s)', $timezone->getName(), $this->formatOffset($this->currentOffset($timezone)));
    }

    /**
     * Format a database timezone.
     *
     * @param  ComparableTimezone  $timezone
     */
    private function formatDatabaseTimezone(array $timezone): string
    {
        if ($timezone['offset'] === null) {
            return $timezone['name'];
        }

        return sprintf('%s (%s)', $timezone['name'], $this->formatOffset($timezone['offset']));
    }

    /**
     * Format an offset in seconds.
     */
    private function formatOffset(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);
        $hours = intdiv($seconds, 60 * 60);
        $minutes = intdiv($seconds % (60 * 60), 60);

        return sprintf('UTC%s%02d:%02d', $sign, $hours, $minutes);
    }
}
