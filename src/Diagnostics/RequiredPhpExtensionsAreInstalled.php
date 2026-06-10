<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class RequiredPhpExtensionsAreInstalled extends Diagnostic
{
    public string $name = 'Required PHP extensions are installed';

    public string $group = 'environment';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $composer = $this->composer();

        if ($composer === null) {
            return DiagnosticResult::skip('The application does not have a readable composer.json file.');
        }

        $missing = array_values(array_filter(
            $this->requiredExtensions($composer),
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

    /**
     * @return array<string, mixed>|null
     */
    private function composer(): ?array
    {
        $contents = @file_get_contents(base_path('composer.json'));

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return null;
        }

        $composer = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $composer[$key] = $value;
            }
        }

        return $composer;
    }

    /**
     * @param  array<string, mixed>  $composer
     * @return list<string>
     */
    private function requiredExtensions(array $composer): array
    {
        $requirements = [
            ...$this->packageNames($composer['require'] ?? []),
            ...$this->packageNames($composer['require-dev'] ?? []),
        ];

        return array_values(array_unique(array_map(
            static fn (string $package): string => substr($package, 4),
            array_filter(
                $requirements,
                static fn (string $package): bool => str_starts_with($package, 'ext-'),
            ),
        )));
    }

    /**
     * @return list<string>
     */
    private function packageNames(mixed $requirements): array
    {
        if (! is_array($requirements)) {
            return [];
        }

        return array_values(array_filter(array_keys($requirements), is_string(...)));
    }
}
