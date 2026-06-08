<?php

namespace Laravel\Doctor\Results;

/**
 * @phpstan-consistent-constructor
 */
class DiagnosticResult
{
    public ?string $details = null;

    /**
     * The remediation messages for the result.
     *
     * @var list<string>
     */
    public array $remediation = [];

    /**
     * The documentation links for the result.
     *
     * @var array<string, string>
     */
    public array $links = [];

    /**
     * The structured context for the result.
     *
     * @var array<string, mixed>
     */
    public array $context = [];

    public ?string $confirmation = null;

    /**
     * Create a new diagnostic result instance.
     */
    public function __construct(
        public Status $status,
        public string $summary,
    ) {
        //
    }

    /**
     * Create a passing diagnostic result.
     */
    public static function pass(string $summary = 'Passed'): static
    {
        return new static(Status::Pass, $summary);
    }

    /**
     * Create a warning diagnostic result.
     */
    public static function warn(string $summary): static
    {
        return new static(Status::Warn, $summary);
    }

    /**
     * Create a notice diagnostic result.
     */
    public static function notice(string $summary): static
    {
        return new static(Status::Notice, $summary);
    }

    /**
     * Create a failing diagnostic result.
     */
    public static function fail(string $summary): static
    {
        return new static(Status::Fail, $summary);
    }

    /**
     * Create a skipped diagnostic result.
     */
    public static function skip(string $summary): static
    {
        return new static(Status::Skip, $summary);
    }

    /**
     * Create an error diagnostic result.
     */
    public static function error(string $summary): static
    {
        return new static(Status::Error, $summary);
    }

    /**
     * Add details to the diagnostic result.
     */
    public function withDetails(string $details): static
    {
        $this->details = $details;

        return $this;
    }

    /**
     * Add remediation text to the diagnostic result.
     */
    public function suggest(string $message): static
    {
        $this->remediation[] = $message;

        return $this;
    }

    /**
     * Set the confirmation prompt for the diagnostic fix.
     */
    public function confirmUsing(string $prompt): static
    {
        $this->confirmation = $prompt;

        return $this;
    }

    /**
     * Add a documentation link to the diagnostic result.
     */
    public function link(string $label, string $url): static
    {
        $this->links[$label] = $url;

        return $this;
    }

    /**
     * Add context to the diagnostic result.
     */
    public function withContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;

        return $this;
    }
}
