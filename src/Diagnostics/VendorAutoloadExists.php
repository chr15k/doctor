<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class VendorAutoloadExists extends Diagnostic
{
    public string $name = 'Composer autoload file exists';

    public string $group = 'composer';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! is_file(base_path('composer.json'))) {
            return DiagnosticResult::skip('The application does not have a composer.json file.');
        }

        if (is_file(base_path('vendor/autoload.php'))) {
            return DiagnosticResult::pass('Composer dependencies are installed.');
        }

        return DiagnosticResult::fail('Composer dependencies are not installed.')
            ->suggest('Install Composer dependencies.');
    }
}
