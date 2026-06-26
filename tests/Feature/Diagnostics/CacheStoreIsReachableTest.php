<?php

use Laravel\Doctor\Diagnostics\CacheStoreIsReachable;

it('passes when the default cache store can be reached', function (): void {
    config([
        'cache.default' => 'array',
        'cache.stores.array' => ['driver' => 'array'],
    ]);

    $result = (new CacheStoreIsReachable)->check();

    expect($result->status->value)->toBe('pass');
});
