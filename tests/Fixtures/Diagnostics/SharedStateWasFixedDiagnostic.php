<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class SharedStateWasFixedDiagnostic extends Diagnostic
{
    public string $name = 'Testing shared state was fixed';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        if (config('doctor-testing.shared-state-fixed') === true) {
            return DiagnosticResult::pass('The shared state dependent diagnostic passed.');
        }

        return DiagnosticResult::fail('The shared state is still broken.');
    }
}
