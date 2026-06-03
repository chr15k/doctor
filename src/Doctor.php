<?php

declare(strict_types=1);

namespace Laravel\Doctor;

use Laravel\Doctor\Contracts\Diagnostic;
use Laravel\Doctor\Results\DiagnosticReport;
use Laravel\Doctor\Support\DiagnosticRegistry;
use Laravel\Doctor\Support\DiagnosticRunner;
use Laravel\Doctor\Support\DiagnosticSelection;

class Doctor
{
    public function __construct(
        private readonly DiagnosticRegistry $registry,
        private readonly DiagnosticRunner $diagnostics,
    ) {
        //
    }

    /**
     * @param  class-string<Diagnostic>  $diagnostic
     */
    public function diagnostic(string $diagnostic): self
    {
        $this->registry->diagnostic($diagnostic);

        return $this;
    }

    /**
     * @param  iterable<class-string<Diagnostic>>  $diagnostics
     */
    public function diagnostics(iterable $diagnostics): self
    {
        $this->registry->diagnostics($diagnostics);

        return $this;
    }

    public function registry(): DiagnosticRegistry
    {
        return $this->registry;
    }

    public function run(?DiagnosticSelection $selection = null): DiagnosticReport
    {
        return $this->diagnostics->runRegistrations(
            $this->registry->registeredDiagnostics(),
            $selection,
        );
    }

    public function diagnosticRunner(): DiagnosticRunner
    {
        return $this->diagnostics;
    }
}
