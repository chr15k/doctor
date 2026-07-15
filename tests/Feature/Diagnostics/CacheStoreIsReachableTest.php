<?php

use Laravel\Doctor\Diagnostics\CacheStoreIsReachable;
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
