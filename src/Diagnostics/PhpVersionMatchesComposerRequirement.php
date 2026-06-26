<?php

namespace Laravel\Doctor\Diagnostics;

use Composer\Semver\Semver;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Support\ComposerJson;

class PhpVersionMatchesComposerRequirement extends Diagnostic
{
    public string $name = 'PHP version matches Composer requirement';

    public string $group = 'environment';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $constraint = $this->composer()->phpConstraint();

        if ($constraint === null) {
            return DiagnosticResult::skip('The application does not declare a PHP version constraint.');
        }

        if ($this->phpVersionSatisfies($constraint)) {
            return DiagnosticResult::pass('The current PHP version satisfies composer.json.');
        }

        return DiagnosticResult::fail('The current PHP version does not satisfy composer.json.')
            ->withDetails(sprintf('PHP %s does not satisfy [%s].', PHP_VERSION, $constraint))
            ->suggest('Use a PHP binary that satisfies the composer.json PHP constraint.');
    }

    /**
     * Determine whether the running PHP version satisfies the constraint.
     */
    private function phpVersionSatisfies(string $constraint): bool
    {
        return Semver::satisfies(PHP_VERSION, $constraint);
    }

    /**
     * Get the Composer manifest reader.
     */
    private function composer(): ComposerJson
    {
        return new ComposerJson;
    }
}
