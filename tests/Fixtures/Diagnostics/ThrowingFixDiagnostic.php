<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use RuntimeException;

class ThrowingFixDiagnostic extends Diagnostic implements Fixable
{
    public string $name = 'Testing diagnostic fix throws';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::fail('The diagnostic failed.')
            ->fixable()
            ->confirmUsing('Fix the testing diagnostic?')
            ->suggest('Apply the testing diagnostic fix.');
    }

    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        throw new RuntimeException('permission denied');
    }
}
