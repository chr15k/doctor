<?php

use Laravel\Doctor\Diagnostics\SessionDriverIsReachable;

function doctor_session_files_path(): string
{
    $path = sys_get_temp_dir().'/laravel-doctor-session-files-'.str_replace('.', '', uniqid('', true));

    mkdir($path, 0775, true);

    return $path;
}

it('passes when file sessions can be reached', function (): void {
    config([
        'session.driver' => 'file',
        'session.files' => doctor_session_files_path(),
    ]);

    $result = (new SessionDriverIsReachable)->check();

    expect($result->status->value)->toBe('pass');
});
