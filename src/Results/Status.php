<?php

declare(strict_types=1);

namespace Laravel\Doctor\Results;

enum Status: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
    case Skip = 'skip';
    case Error = 'error';

    public function failed(): bool
    {
        return $this === self::Fail || $this === self::Error;
    }
}
