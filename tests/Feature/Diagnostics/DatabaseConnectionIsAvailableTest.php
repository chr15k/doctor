<?php

use Laravel\Doctor\Diagnostics\DatabaseConnectionIsAvailable;

it('checks the configured database connection', function (): void {
    config(['database.default' => 'testing']);

    $this->app->instance('db', new class
    {
        public function connection(string $name): object
        {
            return new class
            {
                public function getPdo(): object
                {
                    return new stdClass;
                }
            };
        }
    });

    $result = (new DatabaseConnectionIsAvailable)->check();

    expect($result->status->value)->toBe('pass')
        ->and($result->code)->toBe('database-connection-is-available.reachable');
});
