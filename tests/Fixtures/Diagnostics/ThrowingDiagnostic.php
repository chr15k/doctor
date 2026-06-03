<?php

declare(strict_types=1);

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostics\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use RuntimeException;

final class ThrowingDiagnostic extends Diagnostic
{
    public string $name = 'Testing diagnostic throws';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        throw new RuntimeException('The diagnostic exploded.');
    }
}
