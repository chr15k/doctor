<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\ComposerJson;

class RequiredPhpExtensionsAreInstalled extends Diagnostic
{
    public string $name = 'Required PHP extensions are installed';

    public string $group = 'environment';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'composer-unreadable' => Outcome::skip('The application does not have a readable composer.json file.'),
            'installed' => Outcome::pass('All Composer-required PHP extensions are installed.'),
            'missing' => Outcome::fail(
                summary: 'Some Composer-required PHP extensions are missing.',
                remediation: 'Install the missing PHP extensions for the PHP binary running Laravel.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $composer = new ComposerJson;

        if ($composer->contents() === null) {
            return $this->result('composer-unreadable');
        }

        $missing = array_values(array_filter(
            $composer->requiredExtensions(),
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));

        if ($missing === []) {
            return $this->result('installed');
        }

        return $this->result('missing')
            ->withDetails(implode(PHP_EOL, array_map(
                static fn (string $extension): string => '- ext-'.$extension,
                $missing,
            )));
    }
}
