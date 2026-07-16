<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use RuntimeException;

class ThrowingFixableDiagnostic extends Diagnostic implements Fixable
{
    public string $name = 'Testing fixable diagnostic throws';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        throw new RuntimeException('The fixable diagnostic exploded.');
    }

    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        return FixResult::pass('The diagnostic was fixed.');
    }
}
