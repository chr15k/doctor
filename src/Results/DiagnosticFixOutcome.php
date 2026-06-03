<?php

declare(strict_types=1);

namespace Laravel\Doctor\Results;

use Laravel\Doctor\DiagnosticMetadata;
use Laravel\Doctor\Support\DiagnosticRegistration;

class DiagnosticFixOutcome
{
    public function __construct(
        public readonly DiagnosticMetadata $definition,
        public readonly FixResult $result,
        public readonly ?DiagnosticRegistration $registration = null,
    ) {
        //
    }

    public function source(): string
    {
        return $this->registration?->source() ?? 'internal';
    }
}
