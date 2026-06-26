<?php

use Illuminate\Support\Facades\Redis;
use Laravel\Doctor\Diagnostics\RedisConnectionsAreReachable;

it('passes when configured Redis connections respond', function (): void {
    config([
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
