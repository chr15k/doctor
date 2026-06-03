<?php

declare(strict_types=1);

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostics\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

final class PassingDiagnostic extends Diagnostic
{
    public string $name = 'Testing diagnostic passes';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass('The diagnostic passed.');
    }
}
