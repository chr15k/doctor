<?php

use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;

it('creates versionless Laravel documentation links', function (): void {
    $link = Link::docs('queues');

    expect($link->label)->toBe('Queues documentation')
        ->and($link->url)->toBe('https://laravel.com/docs/queues')
        ->and($link->agentUrl)->toBe('https://laravel.com/docs/queues.md');
});

it('creates section links with custom labels', function (): void {
    $link = Link::docs('queues', 'connections-vs-queues', 'Queue connections');

    expect($link->label)->toBe('Queue connections')
        ->and($link->url)->toBe('https://laravel.com/docs/queues#connections-vs-queues')
        ->and($link->agentUrl)->toBe('https://laravel.com/docs/queues.md');
});

it('uses arbitrary URLs for both people and agents', function (): void {
    $link = Link::to('PHP timezone list', 'https://www.php.net/manual/en/timezones.php');

    expect($link->label)->toBe('PHP timezone list')
        ->and($link->url)->toBe('https://www.php.net/manual/en/timezones.php')
        ->and($link->agentUrl)->toBe('https://www.php.net/manual/en/timezones.php');
});

it('replaces tokens across every link field', function (): void {
    $link = Link::to('{service} documentation', 'https://example.com/{service}')
        ->replace(['{service}' => 'queues']);

    expect($link)->toEqual(Link::to('queues documentation', 'https://example.com/queues'));
});

it('attaches links to diagnostic results', function (): void {
    $link = Link::docs('queues');
    $result = DiagnosticResult::warn('Queue warning')->link($link);

    expect($result->links)->toBe([$link]);
});

it('attaches links fluently to message definitions', function (): void {
    $link = Link::docs('queues');
    $message = Message::make('Queue warning')->link($link);

    expect($message->links)->toBe([$link]);
});
