<?php

declare(strict_types=1);

namespace Laravel\Doctor\Support;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Doctor\Contracts\Diagnostic;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\DiagnosticMetadata;
use Laravel\Doctor\Results\DiagnosticFixOutcome;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use LogicException;
use Throwable;

class DiagnosticRunner
{
    public function __construct(
        private readonly Application $app,
        private readonly DiagnosticRegistry $registry,
    ) {
        //
    }

    public function run(?DiagnosticSelection $selection = null): DiagnosticReport
    {
        return $this->runRegistrations($this->registry->registeredDiagnostics(), $selection);
    }

    /**
     * @param  iterable<DiagnosticRegistration>  $registrations
     */
    public function runRegistrations(
        iterable $registrations,
        ?DiagnosticSelection $selection = null,
    ): DiagnosticReport {
        $outcomes = [];

        foreach ($this->orderedRegistrations($registrations) as $registration) {
            $definition = $this->definition($registration);

            if ($selection !== null && ! $selection->matches($definition, $registration)) {
                continue;
            }

            $outcomes[] = $this->runRegistration($registration, $definition);
        }

        return new DiagnosticReport($outcomes);
    }

    /**
     * @param  iterable<DiagnosticRegistration>  $registrations
     * @return list<DiagnosticRegistration>
     */
    public function orderedRegistrations(iterable $registrations): array
    {
        $internal = [];
        $packages = [];

        foreach ($registrations as $registration) {
            if ($registration->package() === null) {
                $internal[] = $registration;

                continue;
            }

            $packages[] = $registration;
        }

        return [...$internal, ...$packages];
    }

    public function runRegistration(
        DiagnosticRegistration $registration,
        ?DiagnosticMetadata $definition = null,
    ): DiagnosticOutcome {
        $definition ??= $this->definition($registration);

        try {
            $diagnostic = $this->resolveDiagnostic($registration);
            $result = $diagnostic->check();
        } catch (Throwable $e) {
            $result = DiagnosticResult::error($e->getMessage())
                ->withContext('exception', $e::class);
        }

        return new DiagnosticOutcome($definition, $result, $registration);
    }

    public function definition(DiagnosticRegistration $registration): DiagnosticMetadata
    {
        try {
            $diagnostic = $this->resolveDiagnostic($registration);
            $properties = get_object_vars($diagnostic);

            return new DiagnosticMetadata(
                name: $this->stringProperty($properties, 'name') !== '' ? $this->stringProperty($properties, 'name') : $this->humanizeClassName($registration->diagnostic),
                group: $this->stringProperty($properties, 'group', 'general'),
                default: $this->boolProperty($properties, 'default', true),
                timeout: $this->nullableIntProperty($properties, 'timeout'),
            );
        } catch (Throwable) {
            return $this->fallbackDefinition($registration);
        }
    }

    public function isFixable(DiagnosticRegistration $registration): bool
    {
        try {
            return $this->resolveDiagnostic($registration) instanceof Fixable;
        } catch (Throwable) {
            return false;
        }
    }

    public function fixPrompt(DiagnosticRegistration $registration): string
    {
        $default = sprintf('Would you like Doctor to fix "%s"?', $this->definition($registration)->name);

        try {
            $diagnostic = $this->resolveDiagnostic($registration);
        } catch (Throwable) {
            return $default;
        }

        if (property_exists($diagnostic, 'fixPrompt') && is_string($diagnostic->fixPrompt) && $diagnostic->fixPrompt !== '') {
            return $diagnostic->fixPrompt;
        }

        return $default;
    }

    public function fixRegistration(
        DiagnosticRegistration $registration,
        DiagnosticResult $result,
        ?DiagnosticMetadata $definition = null,
    ): DiagnosticFixOutcome {
        $definition ??= $this->definition($registration);

        try {
            $diagnostic = $this->resolveDiagnostic($registration);

            if (! $diagnostic instanceof Fixable) {
                throw new LogicException(sprintf('Diagnostic [%s] does not implement [%s].', $registration->diagnostic, Fixable::class));
            }

            $fix = $diagnostic->fix($result);
        } catch (Throwable $e) {
            $fix = FixResult::error(
                sprintf('Failed to fix %s: %s', $definition->name, $e->getMessage()),
            )->withContext('exception', $e::class);
        }

        return new DiagnosticFixOutcome($definition, $fix, $registration);
    }

    private function resolveDiagnostic(DiagnosticRegistration $registration): Diagnostic
    {
        return $this->app->make($registration->diagnostic);
    }

    private function fallbackDefinition(DiagnosticRegistration $registration): DiagnosticMetadata
    {
        return new DiagnosticMetadata(
            name: $this->shortClassName($registration->diagnostic),
            group: 'unknown',
        );
    }

    private function shortClassName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    private function humanizeClassName(string $class): string
    {
        return trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $this->shortClassName($class)));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function stringProperty(array $properties, string $name, string $default = ''): string
    {
        return isset($properties[$name]) && is_string($properties[$name])
            ? $properties[$name]
            : $default;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function boolProperty(array $properties, string $name, bool $default): bool
    {
        return isset($properties[$name]) && is_bool($properties[$name])
            ? $properties[$name]
            : $default;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function nullableIntProperty(array $properties, string $name): ?int
    {
        return isset($properties[$name]) && is_int($properties[$name])
            ? $properties[$name]
            : null;
    }
}
