<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

class FixesSharedStateDiagnostic extends Diagnostic implements Fixable
{
    public string $name = 'Testing diagnostic fixes shared state';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        if (config('doctor-testing.shared-state-fixed') === true) {
            return DiagnosticResult::pass('The shared state is fixed.');
        }

        return DiagnosticResult::fail('The shared state needs fixing.')
            ->fixable()
            ->suggest('Apply the shared state fix.')
            ->confirmUsing('Fix the shared state?');
    }

    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        config(['doctor-testing.shared-state-fixed' => true]);

        return FixResult::pass('The shared state fix ran.');
    }
}
