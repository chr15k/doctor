<?php

declare(strict_types=1);

namespace Laravel\Doctor\Contracts;

use Laravel\Doctor\Results\DiagnosticResult;

interface Diagnostic
{
    public function check(): DiagnosticResult;
}
