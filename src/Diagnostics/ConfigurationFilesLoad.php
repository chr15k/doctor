<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Throwable;

class ConfigurationFilesLoad extends Diagnostic
{
    public string $name = 'Configuration files load';

    public string $group = 'configuration';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $files = glob(base_path('config/*.php')) ?: [];

        if ($files === []) {
            return DiagnosticResult::skip('The application does not have configuration files.');
        }

        foreach ($files as $file) {
            try {
                require $file;
            } catch (Throwable $e) {
                return DiagnosticResult::fail('A configuration file could not be loaded.')
                    ->withDetails(sprintf('%s: %s', basename($file), $e->getMessage()));
            }
        }

        return DiagnosticResult::pass('Configuration files can be loaded.');
    }
}
