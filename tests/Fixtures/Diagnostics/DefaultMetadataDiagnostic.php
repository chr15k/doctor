<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class DefaultMetadataDiagnostic extends Diagnostic
{
    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass('The diagnostic passed.');
    }
}
