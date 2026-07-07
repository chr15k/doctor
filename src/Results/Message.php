<?php

namespace Laravel\Doctor\Results;

final readonly class Message
{
    /**
     * Create a new diagnostic message definition.
     *
     * @param  array<string, string>  $links
     */
    public function __construct(
        public string $summary,
        public ?string $remediation = null,
        public ?string $confirmation = null,
        public array $links = [],
    ) {
        //
    }

    /**
     * Create a new diagnostic message definition.
     *
     * @param  array<string, string>  $links
     */
    public static function make(
        string $summary,
        ?string $remediation = null,
        ?string $confirmation = null,
        array $links = [],
    ): self {
        return new self($summary, $remediation, $confirmation, $links);
    }
}
