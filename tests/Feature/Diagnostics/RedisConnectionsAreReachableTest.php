<?php

use Illuminate\Support\Facades\Redis;
use Laravel\Doctor\Diagnostics\RedisConnectionsAreReachable;

it('passes when configured Redis connections respond', function (): void {
    config([
        'cache.default' => 'redis',
        'cache.stores.redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],
        'database.redis' => [
            'client' => 'phpredis',
            'default' => [],
        ],
    ]);

    Redis::swap(new class
    {
        public function connection(string $name): object
        {
            return new class
            {
                public function ping(): string
                {
                    return 'PONG';
                }
            };
        }
    });

    $result = (new RedisConnectionsAreReachable)->check();

    expect($result->status->value)->toBe('pass');
});

it('only probes redis connections used by selected services', function (): void {
    config([
        'cache.default' => 'redis',
        'cache.stores.redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
        'queue.default' => 'redis',
        'queue.connections.redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],
        'session.driver' => 'file',
        'database.redis' => [
            'client' => 'phpredis',
            'default' => [],
            'cache' => [],
            'unused' => [],
        ],
    ]);

    $redis = new class
    {
        public array $connections = [];

        public function connection(string $name): object
        {
            $this->connections[] = $name;

            return new class
            {
                public function ping(): string
                {
                    return 'PONG';
                }
            };
        }
    };

    Redis::swap($redis);

    $result = (new RedisConnectionsAreReachable)->check();

    expect($result->status->value)->toBe('pass')
        ->and($redis->connections)->toBe(['cache', 'default']);
});

it('skips when redis is only scaffolded and unused', function (): void {
    config([
        'cache.default' => 'array',
        'queue.default' => 'sync',
        'session.driver' => 'file',
        'database.redis' => [
            'client' => 'phpredis',
            'default' => [],
        ],
    ]);

    $result = (new RedisConnectionsAreReachable)->check();

    expect($result->status->value)->toBe('skip');
});
