<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class ApplicationTimezoneIsValid extends Diagnostic
{
    public string $name = 'Application timezone is valid';

    public string $group = 'environment';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $timezone = config('app.timezone');

        if (! is_string($timezone) || $timezone === '') {
            return DiagnosticResult::fail('Laravel does not have an application timezone configured.')
                ->suggest('Set APP_TIMEZONE or app.timezone to a valid PHP timezone.');
        }

        if ($this->isValidTimezone($timezone)) {
            return DiagnosticResult::pass('Laravel has a valid application timezone.');
        }

        return DiagnosticResult::fail('Laravel has an invalid application timezone.')
            ->withDetails(sprintf('[%s] is not a valid PHP timezone.', $timezone))
            ->suggest('Set app.timezone to one of PHP\'s supported timezone identifiers.');
    }

    /**
     * Determine whether the timezone is recognized by PHP.
     */
    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }
}
