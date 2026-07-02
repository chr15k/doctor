<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Cache;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Throwable;

class CacheStoreIsReachable extends Diagnostic
{
    public string $name = 'Cache connects';

    public string $group = 'cache';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'not-configured' => Outcome::skip('Laravel does not have a default cache store configured.'),
            'unreachable' => Outcome::fail(
                summary: 'Laravel cannot reach the default cache store.',
                remediation: 'Check CACHE_STORE and the backing cache service configuration.',
            ),
            'reachable' => Outcome::pass('Laravel can reach the default cache store.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $store = config()->string('cache.default', '');

        if ($store === '') {
            return $this->result('not-configured');
        }

        try {
            $this->probe($store);
        } catch (Throwable $e) {
            return $this->result('unreachable')
                ->withDetails($e->getMessage());
        }

        return $this->result('reachable');
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
