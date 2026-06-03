<?php

declare(strict_types=1);

namespace Laravel\Doctor\Contracts;

use Laravel\Doctor\Support\Process\ProcessResult;

interface ProcessRunner
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command, ?string $cwd = null, ?int $timeout = null): ProcessResult;
}
