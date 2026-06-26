<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Cache;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Throwable;

class CacheStoreIsReachable extends Diagnostic
{
    public string $name = 'Cache store is reachable';

    public string $group = 'cache';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $store = config('cache.default');

        if (! is_string($store) || $store === '') {
            return DiagnosticResult::skip('Laravel does not have a default cache store configured.');
        }

        try {
            $this->probe($store);
        } catch (Throwable $e) {
            return DiagnosticResult::fail('Laravel cannot reach the default cache store.')
                ->withDetails($e->getMessage())
                ->suggest('Check CACHE_STORE and the backing cache service configuration.');
        }

        return DiagnosticResult::pass('Laravel can reach the default cache store.');
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
