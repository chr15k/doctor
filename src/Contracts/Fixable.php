<?php

namespace Laravel\Doctor\Contracts;

use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

interface Fixable
{
    public function fix(DiagnosticResult $result): FixResult;
}
