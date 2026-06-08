<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class LinkedDiagnostic extends Diagnostic
{
    public string $name = 'Testing diagnostic has links';

    public string $group = 'testing';

    public function check(): DiagnosticResult
    {
        return DiagnosticResult::warn('The linked diagnostic warned.')
            ->withDetails('Detailed link context.')
            ->suggest('Follow the linked documentation.')
            ->link('Laravel Docs', 'https://laravel.com/docs');
    }
}
