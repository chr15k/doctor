<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class PackagedNoticeDiagnostic extends Diagnostic
{
    public string $name = 'Testing packaged diagnostic notices';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::notice('The packaged diagnostic noticed.')
            ->suggest('This packaged notice fixture simulates troubleshooting context.');
    }

    public function package(): ?string
    {
        return 'vendor/package';
    }
}
