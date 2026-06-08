<?php

namespace Laravel\Doctor\Results;

use Laravel\Doctor\Diagnostic;

class DiagnosticOutcome
{
    /**
     * Create a new diagnostic outcome instance.
     */
    public function __construct(
        public Diagnostic $diagnostic,
        public DiagnosticResult $result,
    ) {
        //
    }
}
