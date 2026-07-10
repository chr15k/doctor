<?php

namespace Laravel\Doctor\Results;

/**
 * @phpstan-consistent-constructor
 */
class DiagnosticResult
{
    /**
     * Whether this result represents an outcome the diagnostic can fix.
     */
    public bool $fixable = false;

    public ?string $details = null;

    /**
     * The remediation message for the result.
     */
    public ?string $remediation = null;

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
        public ?string $code = null,
    ) {
        //
    }

    /**
     * Create a passing diagnostic result.
     */
    public static function pass(string $summary = 'Passed', ?string $code = null): static
    {
        return new static(Status::Pass, $summary, $code);
    }

    /**
     * Create a warning diagnostic result.
     */
    public static function warn(string $summary, ?string $code = null): static
    {
        return new static(Status::Warn, $summary, $code);
    }

    /**
     * Create a notice diagnostic result.
     */
    public static function notice(string $summary, ?string $code = null): static
    {
        return new static(Status::Notice, $summary, $code);
    }

    /**
     * Create a failing diagnostic result.
     */
    public static function fail(string $summary, ?string $code = null): static
    {
        return new static(Status::Fail, $summary, $code);
    }

    /**
     * Create a skipped diagnostic result.
     */
    public static function skip(string $summary, ?string $code = null): static
    {
        return new static(Status::Skip, $summary, $code);
    }

    /**
     * Create an error diagnostic result.
     */
    public static function error(string $summary, ?string $code = null): static
    {
        return new static(Status::Error, $summary, $code);
    }

    /**
     * Add evidence to the diagnostic result.
     *
     * Prefer message tokens for short identifying values like versions, paths,
     * constraints, or counts. Use details for unbounded or multi-line evidence
     * such as exception messages, process output, and bullet lists.
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
        $this->remediation = $message;

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
     * Mark this result as eligible for the diagnostic's fix.
     */
    public function fixable(): static
    {
        $this->fixable = true;

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
