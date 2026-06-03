<?php

declare(strict_types=1);

namespace Laravel\Doctor\Results;

use InvalidArgumentException;

final class FixResult
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Status $status,
        public readonly string $summary,
        public readonly ?string $details = null,
        public readonly array $context = [],
    ) {
        if ($summary === '') {
            throw new InvalidArgumentException('Fix result summaries may not be empty.');
        }
    }

    public static function pass(string $summary = 'Fixed'): static
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
        return new static($this->status, $this->summary, $details, $this->context);
    }

    public function withContext(string $key, mixed $value): static
    {
        if ($key === '') {
            throw new InvalidArgumentException('Fix result context keys may not be empty.');
        }

        return new static(
            $this->status,
            $this->summary,
            $this->details,
            [...$this->context, $key => $value],
        );
    }
}
