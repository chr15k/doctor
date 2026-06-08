<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class PackagedDiagnostic extends Diagnostic
{
    public string $name = 'Testing diagnostic from a package';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass('The packaged diagnostic passed.');
    }

    public function package(): ?string
    {
        return 'vendor/package';
    }
}
