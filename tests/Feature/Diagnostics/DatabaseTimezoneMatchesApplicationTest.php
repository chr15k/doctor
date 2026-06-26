<?php

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Laravel\Doctor\Diagnostics\DatabaseTimezoneMatchesApplication;

function doctorTimezoneConnection(string $driver, mixed $selectOne = null, mixed $scalar = null): Connection
{
    return new class($driver, $selectOne, $scalar) extends Connection
    {
        public function __construct(
            private string $driver,
            private mixed $selectOneResult,
            private mixed $scalarResult,
        ) {
            //
        }

        public function getDriverName()
        {
            return $this->driver;
        }

        public function selectOne($query, $bindings = [], $useReadPdo = true)
        {
            return $this->selectOneResult;
        }

        public function scalar($query, $bindings = [], $useReadPdo = true)
        {
            return $this->scalarResult;
        }
    };
}

function doctorTimezoneDatabase(Connection $connection): DatabaseManager
{
    return new class($connection) extends DatabaseManager
    {
        public function __construct(private Connection $connection)
        {
            //
        }

        public function connection($name = null)
        {
            return $this->connection;
        }
    };
}

it('passes when the mysql session timezone matches the application timezone', function (): void {
    config([
        'app.timezone' => 'UTC',
        'database.default' => 'mysql',
    ]);

    $this->app->instance('db', doctorTimezoneDatabase(
        doctorTimezoneConnection('mysql', (object) ['time_zone' => '+00:00', 'offset_seconds' => 0]),
    ));

    $result = (new DatabaseTimezoneMatchesApplication)->check();

    expect($result->status->value)->toBe('pass')
        ->and($result->summary)->toBe('The default database connection uses the application timezone.');
});

it('warns when the mysql session timezone differs from the application timezone', function (): void {
    config([
        'app.timezone' => 'UTC',
        'database.default' => 'mysql',
    ]);

    $this->app->instance('db', doctorTimezoneDatabase(
        doctorTimezoneConnection('mysql', (object) ['time_zone' => '+02:00', 'offset_seconds' => 7200]),
    ));

    $result = (new DatabaseTimezoneMatchesApplication)->check();

    expect($result->status->value)->toBe('warn')
        ->and($result->details)->toContain('Application timezone: UTC')
        ->and($result->details)->toContain('Database timezone: +02:00')
        ->and($result->details)->toContain('Connection: mysql (mysql)');
});

it('checks postgres named timezones', function (): void {
    config([
        'app.timezone' => 'America/New_York',
        'database.default' => 'pgsql',
    ]);

    $this->app->instance('db', doctorTimezoneDatabase(
        doctorTimezoneConnection('pgsql', scalar: 'America/New_York'),
    ));

    $result = (new DatabaseTimezoneMatchesApplication)->check();

    expect($result->status->value)->toBe('pass');
});

it('checks sql server timezone offsets', function (): void {
    config([
        'app.timezone' => 'UTC',
        'database.default' => 'sqlsrv',
    ]);

    $this->app->instance('db', doctorTimezoneDatabase(
        doctorTimezoneConnection('sqlsrv', scalar: -18000),
    ));

    $result = (new DatabaseTimezoneMatchesApplication)->check();

    expect($result->status->value)->toBe('warn')
        ->and($result->details)->toContain('Database timezone: current SQL Server offset (UTC-05:00)')
        ->and($result->details)->toContain('Connection: sqlsrv (sqlsrv)');
});

it('skips sqlite connections', function (): void {
    config([
        'app.timezone' => 'UTC',
        'database.default' => 'sqlite',
    ]);

    $this->app->instance('db', doctorTimezoneDatabase(
        doctorTimezoneConnection('sqlite'),
    ));

    $result = (new DatabaseTimezoneMatchesApplication)->check();

    expect($result->status->value)->toBe('skip')
        ->and($result->summary)->toBe('SQLite does not expose a database timezone.');
});
