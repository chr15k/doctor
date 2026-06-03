<?php

declare(strict_types=1);

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostics\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

final class FixableDiagnostic extends Diagnostic implements Fixable
{
    public string $name = 'Testing diagnostic is fixable';

    public string $group = 'testing';

    public ?string $fixPrompt = 'Fix the testing diagnostic?';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::fail('The diagnostic failed.');
    }

    public function fix(DiagnosticResult $result): FixResult
    {
        return FixResult::pass('The diagnostic was fixed.');
    }
}
