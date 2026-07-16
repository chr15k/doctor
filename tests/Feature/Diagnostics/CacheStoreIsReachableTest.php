<?php

use Illuminate\Support\Facades\Schema;
use Laravel\Doctor\Diagnostics\CacheStoreIsReachable;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;

it('passes when the default cache store can be reached', function (): void {
    config([
        'cache.default' => 'array',
        'cache.stores.array' => ['driver' => 'array'],
    ]);

    $result = (new CacheStoreIsReachable)->check();

    expect($result->status->value)->toBe('pass');
});

it('links to cache configuration when the default store cannot be reached', function (): void {
    config(['cache.default' => 'missing']);

    $result = (new CacheStoreIsReachable)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->links)->toEqual([Link::docs('cache', 'configuration')]);
});

it('offers working alternative stores when the default store is unreachable locally', function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');

    $path = sys_get_temp_dir().'/laravel-doctor-cache-'.str_replace('.', '', uniqid('', true));

    mkdir($path, 0775, true);

    config(['cache' => [
        'default' => 'broken',
        'prefix' => '',
        'stores' => [
            'broken' => ['driver' => 'broken-driver'],
            'file' => ['driver' => 'file', 'path' => $path],
        ],
    ]]);

    $result = (new CacheStoreIsReachable)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->summary)->toBe('The application cannot reach the default cache store [broken].')
        ->and($result->fixable)->toBeTrue()
        ->and($result->fixOptions)->toBe(['file' => 'File'])
        ->and($result->fixDeclineLabel)->toBe('Keep Broken (repair it manually)')
        ->and($result->confirmation)->toBe('Which cache store should the application use?');
});

it('excludes candidates sharing the failing driver even under a custom store name', function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'cache' => [
            'default' => 'custom',
            'prefix' => '',
            'stores' => [
                'custom' => ['driver' => 'database', 'table' => 'cache', 'connection' => null],
                'database' => ['driver' => 'database', 'table' => 'cache', 'connection' => null],
            ],
        ],
    ]);

    $result = (new CacheStoreIsReachable)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->fixable)->toBeFalse()
        ->and($result->fixOptions)->toBe([]);
});

it('leaves the failure non-fixable when no alternative stores are viable', function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');

    config(['cache' => [
        'default' => 'broken',
        'prefix' => '',
        'stores' => [
            'broken' => ['driver' => 'broken-driver'],
        ],
    ]]);

    $result = (new CacheStoreIsReachable)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->fixable)->toBeFalse()
        ->and($result->fixOptions)->toBe([])
        ->and($result->remediation)->toBe('Check CACHE_STORE and the backing cache service configuration.');
});

it('offers the database store with a setup label when only the cache table is missing', function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'cache' => [
            'default' => 'broken',
            'prefix' => '',
            'stores' => [
                'broken' => ['driver' => 'broken-driver'],
                'database' => ['driver' => 'database', 'table' => 'cache', 'connection' => null],
            ],
        ],
    ]);

    $result = (new CacheStoreIsReachable)->check();

    expect($result->fixable)->toBeTrue()
        ->and($result->fixOptions)->toBe(['database' => 'Database (creates the cache table)']);
});

it('creates the cache table before switching to the database store', function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');

    $basePath = sys_get_temp_dir().'/laravel-doctor-cache-switch-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath.'/database/migrations', 0775, true);
    file_put_contents($basePath.'/.env', "CACHE_STORE=broken\n");

    $this->app->setBasePath($basePath);
    $this->app->useEnvironmentPath($basePath);

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'cache' => [
            'default' => 'broken',
            'prefix' => '',
            'stores' => [
                'broken' => ['driver' => 'broken-driver'],
                'database' => ['driver' => 'database', 'table' => 'cache', 'connection' => null],
            ],
        ],
    ]);

    $diagnostic = new CacheStoreIsReachable;
    $result = $diagnostic->check();

    expect($result->fixOptions)->toBe(['database' => 'Database (creates the cache table)']);

    $fix = $diagnostic->fix($result, 'database');

    expect($fix->status->value)->toBe('pass')
        ->and($fix->summary)->toBe('The default cache store was switched to [database].')
        ->and(Schema::hasTable('cache'))->toBeTrue()
        ->and(file_get_contents($basePath.'/.env'))->toContain('CACHE_STORE=database')
        ->and(config('cache.default'))->toBe('database')
        ->and((new CacheStoreIsReachable)->check()->status->value)->toBe('pass');
});

it('switches the default store by writing the environment file', function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');

    $environmentPath = sys_get_temp_dir().'/laravel-doctor-cache-env-'.str_replace('.', '', uniqid('', true));
    $cachePath = $environmentPath.'/cache';

    mkdir($cachePath, 0775, true);
    file_put_contents($environmentPath.'/.env', "CACHE_STORE=broken\n");

    $this->app->useEnvironmentPath($environmentPath);

    config(['cache' => [
        'default' => 'broken',
        'prefix' => '',
        'stores' => [
            'broken' => ['driver' => 'broken-driver'],
            'file' => ['driver' => 'file', 'path' => $cachePath],
        ],
    ]]);

    $diagnostic = new CacheStoreIsReachable;

    $fix = $diagnostic->fix($diagnostic->check(), 'file');

    expect($fix->status->value)->toBe('pass')
        ->and($fix->code)->toBe('cache-store-is-reachable.fix.switched')
        ->and(file_get_contents($environmentPath.'/.env'))->toContain('CACHE_STORE=file')
        ->and(config('cache.default'))->toBe('file');
});

it('fails the switch when the environment file cannot be updated', function (): void {
    $environmentPath = sys_get_temp_dir().'/laravel-doctor-cache-missing-env-'.str_replace('.', '', uniqid('', true));

    mkdir($environmentPath, 0775, true);

    $this->app->useEnvironmentPath($environmentPath);

    $fix = (new CacheStoreIsReachable)->fix(DiagnosticResult::fail('The application cannot reach the default cache store.'), 'file');

    expect($fix->status->value)->toBe('fail')
        ->and($fix->code)->toBe('cache-store-is-reachable.fix.switch-failed')
        ->and($fix->details)->toContain($environmentPath);
});

it('does not switch to a store that fails its re-probe', function (): void {
    $environmentPath = sys_get_temp_dir().'/laravel-doctor-cache-reprobe-'.str_replace('.', '', uniqid('', true));

    mkdir($environmentPath, 0775, true);
    file_put_contents($environmentPath.'/.env', "CACHE_STORE=broken\n");

    $this->app->useEnvironmentPath($environmentPath);

    config(['cache.stores.file' => ['driver' => 'broken-driver']]);

    $fix = (new CacheStoreIsReachable)->fix(DiagnosticResult::fail('The application cannot reach the default cache store.'), 'file');

    expect($fix->status->value)->toBe('fail')
        ->and($fix->code)->toBe('cache-store-is-reachable.fix.switch-failed')
        ->and(file_get_contents($environmentPath.'/.env'))->not->toContain('CACHE_STORE=file');
});

it('does not probe or offer alternative stores for production failures', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    $path = sys_get_temp_dir().'/laravel-doctor-cache-production-'.str_replace('.', '', uniqid('', true));

    mkdir($path, 0775, true);

    config(['cache' => [
        'default' => 'broken',
        'prefix' => '',
        'stores' => [
            'broken' => ['driver' => 'broken-driver'],
            'file' => ['driver' => 'file', 'path' => $path],
        ],
    ]]);

    $result = (new CacheStoreIsReachable)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->fixable)->toBeFalse()
        ->and($result->fixOptions)->toBe([])
        ->and($result->remediation)->toBe('Check CACHE_STORE and the backing cache service configuration.');
});
