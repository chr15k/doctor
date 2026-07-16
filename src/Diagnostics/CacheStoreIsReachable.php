<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\EnvironmentMode;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Configured;
use Throwable;

class CacheStoreIsReachable extends Diagnostic implements Fixable
{
    public string $name = 'Cache connects';

    public string $group = 'cache';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'not-configured' => 'The application does not have a default cache store configured.',
            'unreachable' => Message::make(
                summary: 'The application cannot reach the default cache store [{store}].',
                remediation: 'Check CACHE_STORE and the backing cache service configuration.',
                confirmation: 'Which cache store should the application use?',
            )->link(Link::docs('cache', 'configuration')),
            'reachable' => 'The application can reach the default cache store.',
            'switched' => 'The default cache store was switched to [{store}].',
            'switch-failed' => 'The default cache store could not be switched.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $store = Configured::string('cache.default');

        if ($store === null) {
            return $this->skip('not-configured');
        }

        try {
            $this->probe($store);
        } catch (Throwable $e) {
            $result = $this->fail('unreachable', ['store' => $store])->withDetails($e->getMessage());

            // Building the option list probes candidate stores, so production
            // failures skip the work the fixable(Local) scope would discard.
            if (! EnvironmentMode::current()->isProduction() && ($options = $this->fixOptions($store)) !== []) {
                $result->fixable(EnvironmentMode::Local)->fixOptions(
                    $options,
                    decline: sprintf('Keep %s (repair it manually)', Str::headline($store)),
                );
            }

            return $result;
        }

        return $this->pass('reachable');
    }

    /**
     * Fix the diagnostic by switching the application to the chosen store.
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        /** @var string $option */
        $path = app()->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            return $this->fixFailed('switch-failed')
                ->withDetails(sprintf('The application environment file [%s] could not be updated.', $path));
        }

        if ($option === 'database' && $this->cacheTableIsMissing()) {
            $failure = $this->createCacheTable();

            if ($failure !== null) {
                return $failure;
            }
        }

        try {
            $this->probe($option);
        } catch (Throwable $e) {
            return $this->fixFailed('switch-failed')
                ->withDetails($e->getMessage());
        }

        Env::writeVariable('CACHE_STORE', $option, $path, overwrite: true);

        config(['cache.default' => $option]);

        return $this->fixed('switched', ['store' => $option]);
    }

    /**
     * Get the viable alternative stores for the fix, keyed by store name.
     *
     * A candidate must be configured, use a different driver than the failing
     * store, and survive a probe. The database store is also offered when its
     * connection works but the cache table is missing, since the fix creates
     * the table before switching.
     *
     * @return array<string, string>
     */
    private function fixOptions(string $failing): array
    {
        $failingDriver = Configured::string(sprintf('cache.stores.%s.driver', $failing));

        $options = [];

        foreach (['file' => 'File', 'database' => 'Database', 'redis' => 'Redis', 'memcached' => 'Memcached'] as $store => $label) {
            $driver = Configured::string(sprintf('cache.stores.%s.driver', $store));

            if ($driver === null || $driver === $failingDriver) {
                continue;
            }

            try {
                $this->probe($store);
            } catch (Throwable) {
                if ($store === 'database' && $this->cacheTableIsMissing()) {
                    $options[$store] = 'Database (creates the cache table)';
                }

                continue;
            }

            $options[$store] = $label;
        }

        return $options;
    }

    /**
     * Determine whether the database store's connection works but its cache table is missing.
     */
    private function cacheTableIsMissing(): bool
    {
        $connection = Configured::string('cache.stores.database.connection');
        $table = Configured::string('cache.stores.database.table', 'cache');

        try {
            return ! Schema::connection($connection)->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Create the database store's cache table.
     */
    private function createCacheTable(): ?FixResult
    {
        foreach (['cache:table' => [], 'migrate' => ['--force' => true]] as $command => $parameters) {
            if (Artisan::call($command, $parameters) !== 0) {
                return $this->fixFailed('switch-failed')
                    ->withDetails(trim(Artisan::output()));
            }
        }

        return null;
    }

    /**
     * Probe a cache store with a short-lived key.
     */
    private function probe(string $store): void
    {
        $key = $this->key();

        Cache::store($store)->put($key, true, 10);
        Cache::store($store)->get($key);
        Cache::store($store)->forget($key);
    }

    /**
     * Get a temporary cache key.
     */
    private function key(): string
    {
        return 'laravel-doctor:'.str_replace('.', '', uniqid('', true));
    }
}
