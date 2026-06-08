<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class WarningDiagnostic extends Diagnostic
{
    public string $name = 'Testing diagnostic warns';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::warn('The diagnostic warned.')
            ->withDetails('This warning fixture simulates a non-fixable issue.')
            ->suggest('Re-run this diagnostic after addressing the warning.');
    }
}
