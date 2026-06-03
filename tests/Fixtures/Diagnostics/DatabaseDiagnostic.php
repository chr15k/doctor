<?php

declare(strict_types=1);

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostics\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

final class DatabaseDiagnostic extends Diagnostic
{
    public string $name = 'Database connects';

    public string $group = 'database';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass('The database diagnostic passed.');
    }
}
