<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Support\ComposerJson;

class RecommendedPhpExtensionsAreInstalled extends Diagnostic
{
    public string $name = 'Recommended PHP extensions are installed';

    public string $group = 'environment';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $extensions = $this->composer()->suggestedExtensions();

        if ($extensions === []) {
            return DiagnosticResult::pass('No Composer-suggested PHP extensions are declared.');
        }

        $missing = $this->missingExtensions($extensions);

        if ($missing === []) {
            return DiagnosticResult::pass('All Composer-suggested PHP extensions are installed.');
        }

        return DiagnosticResult::warn('Some Composer-suggested PHP extensions are missing.')
            ->withDetails($this->formatExtensions($missing))
            ->suggest('Install the missing PHP extensions when the related optional features are used.');
    }

    /**
     * Get the missing PHP extensions.
     *
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function missingExtensions(array $extensions): array
    {
        return array_values(array_filter(
            $extensions,
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));
    }

    /**
     * Format extension names for diagnostic details.
     *
     * @param  list<string>  $extensions
     */
    private function formatExtensions(array $extensions): string
    {
        return implode(PHP_EOL, array_map(
            static fn (string $extension): string => '- ext-'.$extension,
            $extensions,
        ));
    }

    /**
     * Get the Composer manifest reader.
     */
    private function composer(): ComposerJson
    {
        return new ComposerJson;
    }
}
