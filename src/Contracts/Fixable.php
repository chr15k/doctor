<?php

namespace Laravel\Doctor\Contracts;

use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

interface Fixable
{
    /**
     * Fix the diagnostic.
     *
     * When the result declares fix options, the option is one of the declared
     * option values; otherwise it is null.
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult;
}
