<?php

declare(strict_types=1);

namespace Laravel\Doctor\Support\Process;

use InvalidArgumentException;
use Laravel\Doctor\Contracts\ProcessRunner;

class NativeProcessRunner implements ProcessRunner
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command, ?string $cwd = null, ?int $timeout = null): ProcessResult
    {
        if ($command === []) {
            throw new InvalidArgumentException('Process commands may not be empty.');
        }

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        /** @var array<int, resource> $pipes */
        $pipes = [];

        $process = proc_open($command, $descriptors, $pipes, $cwd);

        if (! is_resource($process)) {
            return new ProcessResult(1, errorOutput: 'Unable to start process.');
        }

        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return new ProcessResult(
            proc_close($process),
            $output === false ? '' : $output,
            $errorOutput === false ? '' : $errorOutput,
        );
    }
}
