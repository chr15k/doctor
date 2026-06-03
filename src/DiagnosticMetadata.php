<?php

declare(strict_types=1);

namespace Laravel\Doctor;

use InvalidArgumentException;

class DiagnosticMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $group,
        public readonly bool $default = true,
        public readonly ?int $timeout = null,
    ) {
        if ($name === '' || $group === '') {
            throw new InvalidArgumentException('Diagnostic metadata requires a name and group.');
        }
    }
}
