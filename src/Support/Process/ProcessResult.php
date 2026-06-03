<?php

declare(strict_types=1);

namespace Laravel\Doctor\Support\Process;

class ProcessResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output = '',
        public readonly string $errorOutput = '',
    ) {
        //
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
