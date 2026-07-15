<?php

use Laravel\Doctor\Support\Configured;

it('returns configured string values', function (): void {
    config(['services.example.key' => 'secret']);

    expect(Configured::string('services.example.key'))->toBe('secret');
});

it('falls back to the default for blank or non-string values', function (): void {
    config([
        'services.example.empty' => '',
        'services.example.array' => ['nested'],
    ]);

    expect(Configured::string('services.example.empty'))->toBeNull()
        ->and(Configured::string('services.example.array'))->toBeNull()
        ->and(Configured::string('services.example.absent', 'default'))->toBe('default');
});

it('reports configuration keys without a usable value as missing', function (): void {
    config([
        'services.example.null' => null,
        'services.example.blank' => '  ',
        'services.example.empty-array' => [],
    ]);

    expect(Configured::missing([
        'services.example.null',
        'services.example.blank',
        'services.example.empty-array',
        'services.example.absent',
    ]))->toBe([
        'services.example.null',
        'services.example.blank',
        'services.example.empty-array',
        'services.example.absent',
    ]);
});

it('does not report keys holding usable values as missing', function (): void {
    config([
        'services.example.string' => 'value',
        'services.example.false' => false,
        'services.example.zero' => 0,
        'services.example.array' => ['nested'],
    ]);

    expect(Configured::missing([
        'services.example.string',
        'services.example.false',
        'services.example.zero',
        'services.example.array',
    ]))->toBe([]);
});
