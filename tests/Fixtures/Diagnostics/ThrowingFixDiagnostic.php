<?php

declare(strict_types=1);

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostics\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use RuntimeException;

final class ThrowingFixDiagnostic extends Diagnostic implements Fixable
{
    public string $name = 'Testing diagnostic fix throws';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::fail('The diagnostic failed.');
    }

    public function fix(DiagnosticResult $result): FixResult
    {
        throw new RuntimeException('permission denied');
    }
}
