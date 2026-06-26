<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class LaravelPackageDiagnostic extends Diagnostic
{
    public string $name = 'Testing diagnostic from a Laravel package';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass('The Laravel package diagnostic passed.');
    }

    public function package(): ?string
    {
        return 'laravel/horizon';
    }
}
