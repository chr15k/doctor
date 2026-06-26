<?php

namespace Laravel\Doctor\Results;

final readonly class FixOutcome
{
    /**
     * Create a new fix outcome definition.
     */
    public function __construct(
        public Status $status,
        public string $summary,
    ) {
        //
    }

    /**
     * Create a passing fix outcome definition.
     */
    public static function pass(string $summary = 'Fixed'): self
    {
        return new self(Status::Pass, $summary);
    }

    /**
     * Create a warning fix outcome definition.
     */
    public static function warn(string $summary): self
    {
        return new self(Status::Warn, $summary);
    }

    /**
     * Create a notice fix outcome definition.
     */
    public static function notice(string $summary): self
    {
        return new self(Status::Notice, $summary);
    }

    /**
     * Create a failing fix outcome definition.
     */
    public static function fail(string $summary): self
    {
        return new self(Status::Fail, $summary);
    }

    /**
     * Create a skipped fix outcome definition.
     */
    public static function skip(string $summary): self
    {
        return new self(Status::Skip, $summary);
    }

    /**
     * Create an error fix outcome definition.
     */
    public static function error(string $summary): self
    {
        return new self(Status::Error, $summary);
    }
}
