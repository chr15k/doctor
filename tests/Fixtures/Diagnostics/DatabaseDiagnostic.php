<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class DatabaseDiagnostic extends Diagnostic
{
    public string $name = 'Database connects';

    public string $group = 'database';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass('The database diagnostic passed.');
    }
}
