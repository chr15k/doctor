<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Throwable;

class ConfigurationFilesCanBeLoaded extends Diagnostic
{
    public string $name = 'Config files load';

    public string $group = 'configuration';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'missing' => Outcome::skip('The application does not have configuration files.'),
            'load-failed' => Outcome::fail('A configuration file could not be loaded.'),
            'loaded' => Outcome::pass('Configuration files can be loaded.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $files = glob(base_path('config/*.php')) ?: [];

        if ($files === []) {
            return $this->result('missing');
        }

        foreach ($files as $file) {
            try {
                require $file;
            } catch (Throwable $e) {
                return $this->result('load-failed')
                    ->withDetails(sprintf('%s: %s', basename($file), $e->getMessage()));
            }
        }

        return $this->result('loaded');
    }
}
