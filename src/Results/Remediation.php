<?php

declare(strict_types=1);

namespace Laravel\Doctor\Results;

use InvalidArgumentException;

class Remediation
{
    private function __construct(
        public readonly string $message,
        public readonly ?string $command = null,
    ) {
        if ($message === '') {
            throw new InvalidArgumentException('Remediation messages may not be empty.');
        }
    }

    public static function message(string $message): self
    {
        return new self($message);
    }

    public static function command(string $command, ?string $message = null): self
    {
        if ($command === '') {
            throw new InvalidArgumentException('Remediation commands may not be empty.');
        }

        return new self($message ?? $command, $command);
    }

    public function isCommand(): bool
    {
        return $this->command !== null;
    }
}
