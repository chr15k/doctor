<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class NoticeDiagnostic extends Diagnostic
{
    public string $name = 'Testing diagnostic notices';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::notice('The diagnostic noticed.')
            ->suggest('This notice fixture simulates troubleshooting context.');
    }
}
