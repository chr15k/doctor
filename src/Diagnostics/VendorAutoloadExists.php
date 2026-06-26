<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;

class VendorAutoloadExists extends Diagnostic
{
    public string $name = 'Composer autoload file exists';

    public string $group = 'composer';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'composer-missing' => Outcome::skip('The application does not have a composer.json file.'),
            'installed' => Outcome::pass('Composer dependencies are installed.'),
            'missing' => Outcome::fail(
                summary: 'Composer dependencies are not installed.',
                remediation: 'Install Composer dependencies.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! is_file(base_path('composer.json'))) {
            return $this->result('composer-missing');
        }

        if (is_file(base_path('vendor/autoload.php'))) {
            return $this->result('installed');
        }

        return $this->result('missing');
    }
}
