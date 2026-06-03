<?php

declare(strict_types=1);

namespace Laravel\Doctor\Support;

use InvalidArgumentException;
use Laravel\Doctor\Contracts\Diagnostic;

class DiagnosticRegistry
{
    /**
     * @var array<class-string<Diagnostic>, DiagnosticRegistration>
     */
    private array $diagnostics = [];

    /**
     * @param  class-string  $diagnostic
     */
    public function diagnostic(string $diagnostic): self
    {
        $this->ensureDiagnostic($diagnostic);

        $this->diagnostics[$diagnostic] = new DiagnosticRegistration($diagnostic);

        return $this;
    }

    /**
     * @param  iterable<class-string>  $diagnostics
     */
    public function diagnostics(iterable $diagnostics): self
    {
        foreach ($diagnostics as $diagnostic) {
            $this->diagnostic($diagnostic);
        }

        return $this;
    }

    /**
     * @return list<DiagnosticRegistration>
     */
    public function registeredDiagnostics(): array
    {
        return array_values($this->diagnostics);
    }

    /**
     * @return list<class-string<Diagnostic>>
     */
    public function diagnosticClasses(): array
    {
        /** @var list<class-string<Diagnostic>> $diagnostics */
        $diagnostics = array_map(
            static fn (DiagnosticRegistration $registration): string => $registration->diagnostic,
            $this->registeredDiagnostics(),
        );

        return $diagnostics;
    }

    /**
     * @param  class-string  $diagnostic
     *
     * @phpstan-assert class-string<Diagnostic> $diagnostic
     */
    private function ensureDiagnostic(string $diagnostic): void
    {
        if (! is_a($diagnostic, Diagnostic::class, true)) {
            throw new InvalidArgumentException('Diagnostics must implement ['.Diagnostic::class.'].');
        }
    }
}
