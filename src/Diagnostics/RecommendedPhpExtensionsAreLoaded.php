<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\ComposerJson;

class RecommendedPhpExtensionsAreLoaded extends Diagnostic
{
    public string $name = 'Has recommended PHP extensions';

    public string $group = 'environment';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'none-declared' => Outcome::pass('No Composer-suggested PHP extensions are declared.'),
            'installed' => Outcome::pass('All Composer-suggested PHP extensions are installed.'),
            'missing' => Outcome::warn(
                summary: 'Some Composer-suggested PHP extensions are missing.',
                remediation: 'Install the missing PHP extensions when the related optional features are used.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $extensions = $this->composer()->suggestedExtensions();

        if ($extensions === []) {
            return $this->result('none-declared');
        }

        $missing = $this->missingExtensions($extensions);

        if ($missing === []) {
            return $this->result('installed');
        }

        return $this->result('missing')
            ->withDetails($this->formatExtensions($missing));
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
