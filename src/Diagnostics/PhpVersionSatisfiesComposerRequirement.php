<?php

namespace Laravel\Doctor\Diagnostics;

use Composer\Semver\Semver;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\ComposerJson;

class PhpVersionSatisfiesComposerRequirement extends Diagnostic
{
    public string $name = 'PHP satisfies Composer';

    public string $group = 'environment';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'constraint-missing' => 'The application does not declare a PHP version constraint.',
            'satisfied' => 'The current PHP version satisfies composer.json.',
            'unsatisfied' => Message::make(
                summary: 'PHP {version} does not satisfy [{constraint}].',
                remediation: 'Use a PHP binary that satisfies the composer.json PHP constraint.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $constraint = (new ComposerJson)->phpConstraint();

        if ($constraint === null) {
            return $this->skip('constraint-missing');
        }

        if (Semver::satisfies(PHP_VERSION, $constraint)) {
            return $this->pass('satisfied');
        }

        return $this->fail('unsatisfied', [
            'version' => PHP_VERSION,
            'constraint' => $constraint,
        ]);
    }
}
