<?php

use Laravel\Doctor\Diagnostics\FilesystemDisksAreReachable;

function doctor_filesystem_disk_root(): string
{
    $path = sys_get_temp_dir().'/laravel-doctor-filesystem-disk-'.str_replace('.', '', uniqid('', true));

    mkdir($path, 0775, true);

    return $path;
}

it('passes when local filesystem disks are reachable', function (): void {
    config([
        'filesystems.disks' => [
            'doctor' => [
                'driver' => 'local',
                'root' => doctor_filesystem_disk_root(),
            ],
        ],
    ]);

    $result = (new FilesystemDisksAreReachable)->check();

    expect($result->status->value)->toBe('pass');
});
