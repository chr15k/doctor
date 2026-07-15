<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;
use Throwable;

class ConfigurationFilesCanBeLoaded extends Diagnostic
{
    public string $name = 'Config files load';

    public string $group = 'configuration';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'missing' => 'The application does not have configuration files.',
            'load-failed' => Message::make(
                summary: 'A configuration file could not be loaded.',
            )->link(Link::docs('configuration')),
            'loaded' => 'Configuration files can be loaded.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $files = glob(base_path('config/*.php')) ?: [];

        if ($files === []) {
            return $this->skip('missing');
        }

        foreach ($files as $file) {
            try {
                require $file;
            } catch (Throwable $e) {
                return $this->fail('load-failed')
                    ->withDetails(sprintf('%s: %s', basename($file), $e->getMessage()));
            }
        }

        return $this->pass('loaded');
    }
}
