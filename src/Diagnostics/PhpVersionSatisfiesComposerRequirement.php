<?php

namespace Laravel\Doctor\Diagnostics;

use Composer\Semver\Semver;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\ComposerJson;

class PhpVersionSatisfiesComposerRequirement extends Diagnostic
{
    public string $name = 'PHP satisfies Composer';

    public string $group = 'environment';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'constraint-missing' => Outcome::skip('The application does not declare a PHP version constraint.'),
            'satisfied' => Outcome::pass('The current PHP version satisfies composer.json.'),
            'unsatisfied' => Outcome::fail(
                summary: 'The current PHP version does not satisfy composer.json.',
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
            return $this->result('constraint-missing');
        }

        if (Semver::satisfies(PHP_VERSION, $constraint)) {
            return $this->result('satisfied');
        }

        return $this->result('unsatisfied')
            ->withDetails(sprintf('PHP %s does not satisfy [%s].', PHP_VERSION, $constraint));
    }
}
