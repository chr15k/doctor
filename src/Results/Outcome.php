<?php

namespace Laravel\Doctor\Results;

final readonly class Outcome
{
    /**
     * Create a new outcome definition.
     *
     * @param  array<string, string>  $links
     */
    public function __construct(
        public Status $status,
        public string $summary,
        public ?string $remediation = null,
        public ?string $confirmation = null,
        public array $links = [],
    ) {
        //
    }

    /**
     * Create a passing outcome definition.
     */
    public static function pass(string $summary = 'Passed'): self
    {
        return new self(Status::Pass, $summary);
    }

    /**
     * Create a warning outcome definition.
     */
    public static function warn(string $summary, ?string $remediation = null): self
    {
        return new self(Status::Warn, $summary, remediation: $remediation);
    }

    /**
     * Create a notice outcome definition.
     */
    public static function notice(string $summary, ?string $remediation = null): self
    {
        return new self(Status::Notice, $summary, remediation: $remediation);
    }

    /**
     * Create a failing outcome definition.
     */
    public static function fail(string $summary, ?string $remediation = null, ?string $confirmation = null): self
    {
        return new self(Status::Fail, $summary, remediation: $remediation, confirmation: $confirmation);
    }

    /**
     * Create a skipped outcome definition.
     */
    public static function skip(string $summary): self
    {
        return new self(Status::Skip, $summary);
    }

    /**
     * Create an error outcome definition.
     */
    public static function error(string $summary): self
    {
        return new self(Status::Error, $summary);
    }
}
