<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;

class ApplicationTimezoneIsValid extends Diagnostic
{
    public string $name = 'App timezone is valid';

    public string $group = 'environment';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'missing' => Outcome::fail(
                summary: 'Laravel does not have an application timezone configured.',
                remediation: 'Set APP_TIMEZONE or app.timezone to a valid PHP timezone.',
            ),
            'valid' => Outcome::pass('Laravel has a valid application timezone.'),
            'invalid' => Outcome::fail(
                summary: 'Laravel has an invalid application timezone.',
                remediation: 'Set app.timezone to one of PHP\'s supported timezone identifiers.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $timezone = config('app.timezone');

        if (! is_string($timezone) || $timezone === '') {
            return $this->result('missing');
        }

        if ($this->isValidTimezone($timezone)) {
            return $this->result('valid');
        }

        return $this->result('invalid')
            ->withDetails(sprintf('[%s] is not a valid PHP timezone.', $timezone));
    }

    /**
     * Determine whether the timezone is recognized by PHP.
     */
    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }
}
