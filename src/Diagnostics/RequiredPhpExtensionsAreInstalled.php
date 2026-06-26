<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Support\ComposerJson;

class RequiredPhpExtensionsAreInstalled extends Diagnostic
{
    public string $name = 'Required PHP extensions are installed';

    public string $group = 'environment';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $composer = new ComposerJson;

        if ($composer->contents() === null) {
            return DiagnosticResult::skip('The application does not have a readable composer.json file.');
        }

        $missing = array_values(array_filter(
            $composer->requiredExtensions(),
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));

        if ($missing === []) {
            return DiagnosticResult::pass('All Composer-required PHP extensions are installed.');
        }

        return DiagnosticResult::fail('Some Composer-required PHP extensions are missing.')
            ->withDetails(implode(PHP_EOL, array_map(
                static fn (string $extension): string => '- ext-'.$extension,
                $missing,
            )))
            ->suggest('Install the missing PHP extensions for the PHP binary running Laravel.');
    }
}
