<?php

declare(strict_types=1);

namespace Laravel\Doctor\Results;

use InvalidArgumentException;

final class DiagnosticResult
{
    /**
     * @param  list<Remediation>  $remediation
     * @param  array<string, string>  $links
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Status $status,
        public readonly string $summary,
        public readonly ?string $details = null,
        public readonly array $remediation = [],
        public readonly array $links = [],
        public readonly array $context = [],
    ) {
        if ($summary === '') {
            throw new InvalidArgumentException('Diagnostic result summaries may not be empty.');
        }
    }

    public static function pass(string $summary = 'Passed'): static
    {
        return new self(Status::Pass, $summary);
    }

    public static function warn(string $summary): static
    {
        return new static(Status::Warn, $summary);
    }

    public static function fail(string $summary): static
    {
        return new static(Status::Fail, $summary);
    }

    public static function skip(string $summary): static
    {
        return new static(Status::Skip, $summary);
    }

    public static function error(string $summary): static
    {
        return new static(Status::Error, $summary);
    }

    public function withDetails(string $details): static
    {
        return new static(
            $this->status,
            $this->summary,
            $details,
            $this->remediation,
            $this->links,
            $this->context,
        );
    }

    public function suggest(string $message): static
    {
        return $this->withRemediation(Remediation::message($message));
    }

    public function command(string $command, ?string $message = null): static
    {
        return $this->withRemediation(Remediation::command($command, $message));
    }

    public function link(string $label, string $url): static
    {
        if ($label === '' || $url === '') {
            throw new InvalidArgumentException('Diagnostic result links require a label and URL.');
        }

        return new static(
            $this->status,
            $this->summary,
            $this->details,
            $this->remediation,
            [...$this->links, $label => $url],
            $this->context,
        );
    }

    public function withContext(string $key, mixed $value): static
    {
        if ($key === '') {
            throw new InvalidArgumentException('Diagnostic result context keys may not be empty.');
        }

        return new static(
            $this->status,
            $this->summary,
            $this->details,
            $this->remediation,
            $this->links,
            [...$this->context, $key => $value],
        );
    }

    private function withRemediation(Remediation $remediation): static
    {
        return new static(
            $this->status,
            $this->summary,
            $this->details,
            [...$this->remediation, $remediation],
            $this->links,
            $this->context,
        );
    }
}
