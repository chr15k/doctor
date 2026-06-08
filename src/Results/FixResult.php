<?php

namespace Laravel\Doctor\Results;

/**
 * @phpstan-consistent-constructor
 */
class FixResult
{
    public ?string $details = null;

    /**
     * The structured context for the result.
     *
     * @var array<string, mixed>
     */
    public array $context = [];

    /**
     * Create a new fix result instance.
     */
    public function __construct(
        public Status $status,
        public string $summary,
    ) {
        //
    }

    /**
     * Create a passing fix result.
     */
    public static function pass(string $summary = 'Fixed'): static
    {
        return new static(Status::Pass, $summary);
    }

    /**
     * Create a warning fix result.
     */
    public static function warn(string $summary): static
    {
        return new static(Status::Warn, $summary);
    }

    /**
     * Create a notice fix result.
     */
    public static function notice(string $summary): static
    {
        return new static(Status::Notice, $summary);
    }

    /**
     * Create a failing fix result.
     */
    public static function fail(string $summary): static
    {
        return new static(Status::Fail, $summary);
    }

    /**
     * Create a skipped fix result.
     */
    public static function skip(string $summary): static
    {
        return new static(Status::Skip, $summary);
    }

    /**
     * Create an error fix result.
     */
    public static function error(string $summary): static
    {
        return new static(Status::Error, $summary);
    }

    /**
     * Add details to the fix result.
     */
    public function withDetails(string $details): static
    {
        $this->details = $details;

        return $this;
    }

    /**
     * Add context to the fix result.
     */
    public function withContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;

        return $this;
    }
}
