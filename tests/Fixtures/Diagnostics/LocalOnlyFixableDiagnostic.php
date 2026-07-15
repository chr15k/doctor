<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\EnvironmentMode;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

class LocalOnlyFixableDiagnostic extends Diagnostic implements Fixable
{
    public string $name = 'Testing diagnostic is fixable locally';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::fail('The local diagnostic failed.')
            ->fixable(EnvironmentMode::Local)
            ->confirmUsing('Fix the local testing diagnostic?')
            ->suggest('Apply the local testing diagnostic fix.');
    }

    public function fix(DiagnosticResult $result): FixResult
    {
        return FixResult::pass('The local diagnostic was fixed.');
    }
}
