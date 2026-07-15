<?php

use Laravel\Doctor\Diagnostics\RequiredConfigurationValuesAreSet;
use Laravel\Doctor\Results\Link;
use Monolog\Handler\SyslogUdpHandler;

it('passes with the default configuration', function (): void {
    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('pass');
});

it('reports missing credentials for the active pusher broadcast connection', function (): void {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.driver' => 'pusher',
        'broadcasting.connections.pusher.app_id' => null,
        'broadcasting.connections.pusher.key' => '',
        'broadcasting.connections.pusher.secret' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('broadcasting.connections.pusher.app_id')
        ->and($result->details)->toContain('broadcasting.connections.pusher.key')
        ->and($result->details)->toContain('broadcasting.connections.pusher.secret')
        ->and($result->links)->toEqual([Link::docs('configuration', 'environment-configuration')]);
});

it('passes when the active broadcast connection is fully configured', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.driver' => 'reverb',
        'broadcasting.connections.reverb.app_id' => '12345',
        'broadcasting.connections.reverb.key' => 'key',
        'broadcasting.connections.reverb.secret' => 'secret',
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('pass');
});

it('ignores credentials for inactive broadcast connections', function (): void {
    config([
        'broadcasting.default' => 'null',
        'broadcasting.connections.null.driver' => 'null',
        'broadcasting.connections.pusher.driver' => 'pusher',
        'broadcasting.connections.pusher.key' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('pass');
});

it('resolves renamed broadcast connections by driver', function (): void {
    config([
        'broadcasting.default' => 'websockets',
        'broadcasting.connections.websockets.driver' => 'pusher',
        'broadcasting.connections.websockets.app_id' => null,
        'broadcasting.connections.websockets.key' => null,
        'broadcasting.connections.websockets.secret' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('broadcasting.connections.websockets.app_id')
        ->and($result->details)->toContain('broadcasting.connections.websockets.key')
        ->and($result->details)->toContain('broadcasting.connections.websockets.secret');
});

it('requires the ably key for ably broadcast connections', function (): void {
    config([
        'broadcasting.default' => 'ably',
        'broadcasting.connections.ably.driver' => 'ably',
        'broadcasting.connections.ably.key' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('broadcasting.connections.ably.key')
        ->and($result->details)->not->toContain('secret');
});

it('requires a bucket and region for the default s3 filesystem disk', function (): void {
    config([
        'filesystems.default' => 'media',
        'filesystems.disks.media.driver' => 's3',
        'filesystems.disks.media.bucket' => null,
        'filesystems.disks.media.region' => null,
        'filesystems.disks.media.key' => null,
        'filesystems.disks.media.secret' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('filesystems.disks.media.bucket')
        ->and($result->details)->toContain('filesystems.disks.media.region')
        ->and($result->details)->not->toContain('filesystems.disks.media.key')
        ->and($result->details)->not->toContain('filesystems.disks.media.secret');
});

it('ignores s3 values when the default disk is local', function (): void {
    config([
        'filesystems.default' => 'local',
        'filesystems.disks.local.driver' => 'local',
        'filesystems.disks.s3.driver' => 's3',
        'filesystems.disks.s3.bucket' => null,
        'filesystems.disks.s3.region' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('pass');
});

it('requires the webhook url for active slack log channels', function (): void {
    config([
        'logging.default' => 'stack',
        'logging.channels.stack.driver' => 'stack',
        'logging.channels.stack.channels' => ['single', 'alerts'],
        'logging.channels.single.driver' => 'single',
        'logging.channels.alerts.driver' => 'slack',
        'logging.channels.alerts.url' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('logging.channels.alerts.url');
});

it('ignores inactive log channels', function (): void {
    config([
        'logging.default' => 'single',
        'logging.channels.single.driver' => 'single',
        'logging.channels.slack.driver' => 'slack',
        'logging.channels.slack.url' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('pass');
});

it('requires a host and port for syslog udp log channels', function (): void {
    config([
        'logging.default' => 'papertrail',
        'logging.channels.papertrail.driver' => 'monolog',
        'logging.channels.papertrail.handler' => SyslogUdpHandler::class,
        'logging.channels.papertrail.handler_with.host' => null,
        'logging.channels.papertrail.handler_with.port' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('logging.channels.papertrail.handler_with.host')
        ->and($result->details)->toContain('logging.channels.papertrail.handler_with.port');
});

it('requires the postmark token for the active mailer', function (): void {
    config([
        'mail.default' => 'postmark',
        'mail.mailers.postmark.transport' => 'postmark',
        'services.postmark.token' => null,
        'services.resend.key' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('services.postmark.token')
        ->and($result->details)->not->toContain('services.resend.key');
});

it('resolves mailers behind failover transports', function (): void {
    config([
        'mail.default' => 'failover',
        'mail.mailers.failover.transport' => 'failover',
        'mail.mailers.failover.mailers' => ['transactional', 'log'],
        'mail.mailers.transactional.transport' => 'resend',
        'mail.mailers.log.transport' => 'log',
        'services.resend.key' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('services.resend.key');
});

it('ignores credentials for unused mailers', function (): void {
    config([
        'mail.default' => 'log',
        'mail.mailers.log.transport' => 'log',
        'mail.mailers.postmark.transport' => 'postmark',
        'services.postmark.token' => null,
        'services.resend.key' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('pass');
});

it('requires a host for the active smtp mailer', function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.host' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('mail.mailers.smtp.host');
});

it('requires an overflow store when sqs queue overflow is enabled', function (): void {
    config([
        'queue.default' => 'jobs',
        'queue.connections.jobs.driver' => 'sqs',
        'queue.connections.jobs.overflow.enabled' => true,
        'queue.connections.jobs.overflow.store' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('fail')
        ->and($result->details)->toContain('queue.connections.jobs.overflow.store');
});

it('ignores the overflow store when sqs queue overflow is disabled', function (): void {
    config([
        'queue.default' => 'jobs',
        'queue.connections.jobs.driver' => 'sqs',
        'queue.connections.jobs.overflow.enabled' => false,
        'queue.connections.jobs.overflow.store' => null,
    ]);

    $result = (new RequiredConfigurationValuesAreSet)->check();

    expect($result->status->value)->toBe('pass');
});
