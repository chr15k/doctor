<?php

namespace Laravel\Doctor\Results;

use Laravel\Doctor\Diagnostic;

class DiagnosticFixOutcome
{
    /**
     * Create a new diagnostic fix outcome instance.
     */
    public function __construct(
        public Diagnostic $diagnostic,
        public FixResult $result,
    ) {
        //
    }
}
