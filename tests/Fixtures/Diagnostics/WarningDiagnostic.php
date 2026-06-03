<?php

declare(strict_types=1);

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostics\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

final class WarningDiagnostic extends Diagnostic
{
    public string $name = 'Testing diagnostic warns';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::warn('The diagnostic warned.')
            ->withDetails('This warning fixture simulates a non-fixable issue.')
            ->command('php artisan doctor --only=WarningDiagnostic', 'Re-run this diagnostic after addressing the warning.');
    }
}
