<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

class OptionFixableDiagnostic extends Diagnostic implements Fixable
{
    public string $name = 'Testing diagnostic offers fix options';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        if (config('doctor-testing.option-fixed') !== null) {
            return DiagnosticResult::pass('The option diagnostic is fixed.');
        }

        return DiagnosticResult::fail('The option diagnostic failed.')
            ->fixable()
            ->fixOptions(['a' => 'Option A', 'b' => 'Option B'])
            ->confirmUsing('Which option should fix the diagnostic?')
            ->suggest('Apply one of the diagnostic fix options.');
    }

    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        config(['doctor-testing.option-fixed' => $option]);

        return FixResult::pass(sprintf('The option diagnostic was fixed with [%s].', $option));
    }
}
