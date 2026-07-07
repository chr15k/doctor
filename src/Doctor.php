<?php

namespace Laravel\Doctor;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Results\DiagnosticFixOutcome;
use Laravel\Doctor\Results\DiagnosticOutcome;
use Laravel\Doctor\Results\DiagnosticReport;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use LogicException;
use Throwable;

class Doctor
{
    /**
     * The registered diagnostic classes.
     *
     * @var array<class-string<Diagnostic>, class-string<Diagnostic>>
     */
    protected array $diagnostics = [];

    /**
     * The resolved diagnostic instances.
     *
     * @var array<class-string<Diagnostic>, Diagnostic>
     */
    protected array $instances = [];

    /**
     * Create a new Doctor instance.
     */
    public function __construct(protected Container $container)
    {
        //
    }

    /**
     * Register a diagnostic.
     *
     * @param  class-string  $diagnostic
     */
    public function diagnostic(string $diagnostic): self
    {
        if (! is_subclass_of($diagnostic, Diagnostic::class)) {
            throw new InvalidArgumentException('Diagnostics must extend ['.Diagnostic::class.'].');
        }

        $this->diagnostics[$diagnostic] = $diagnostic;

        return $this;
    }

    /**
     * Register multiple diagnostics.
     *
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
     * Get the registered diagnostic classes.
     *
     * @return list<class-string<Diagnostic>>
     */
    public function registered(): array
    {
        return array_values($this->diagnostics);
    }

    /**
     * Determine whether any diagnostics are registered.
     */
    public function hasDiagnostics(): bool
    {
        return $this->diagnostics !== [];
    }

    /**
     * Run the registered diagnostics.
     */
    public function run(?DiagnosticSelection $selection = null): DiagnosticReport
    {
        $outcomes = [];

        foreach ($this->selectedByGroup($selection) as $diagnostics) {
            foreach ($diagnostics as $diagnostic) {
                $outcomes[] = $this->check($diagnostic);
            }
        }

        return new DiagnosticReport($outcomes);
    }

    /**
     * Run a single diagnostic.
     */
    public function check(Diagnostic $diagnostic): DiagnosticOutcome
    {
        try {
            $result = $diagnostic->check();
        } catch (Throwable $e) {
            $result = DiagnosticResult::error($e->getMessage())
                ->withContext('exception', $e::class);
        }

        return new DiagnosticOutcome($diagnostic, $result);
    }

    /**
     * Run the fix for a diagnostic outcome.
     */
    public function fix(DiagnosticOutcome $outcome): DiagnosticFixOutcome
    {
        $diagnostic = $outcome->diagnostic;

        if (! $diagnostic instanceof Fixable) {
            throw new LogicException(sprintf('Diagnostic [%s] does not implement [%s].', $diagnostic::class, Fixable::class));
        }

        try {
            $result = $diagnostic->fix($outcome->result);
        } catch (Throwable $e) {
            $result = FixResult::error(
                sprintf('Failed to fix %s: %s', $diagnostic->name, $e->getMessage()),
            )->withContext('exception', $e::class);
        }

        return new DiagnosticFixOutcome($diagnostic, $result);
    }

    /**
     * Get the selected diagnostics grouped by diagnostic group.
     *
     * @return array<string, list<Diagnostic>>
     */
    public function selectedByGroup(?DiagnosticSelection $selection = null): array
    {
        $groups = [];

        foreach ($this->selected($selection) as $diagnostic) {
            $groups[$diagnostic->group][] = $diagnostic;
        }

        return $groups;
    }

    /**
     * Get the available diagnostic groups.
     *
     * @return array<string, string>
     */
    public function availableGroups(?DiagnosticSelection $selection = null): array
    {
        $groups = [];

        foreach ($this->selected($selection) as $diagnostic) {
            $groups[$diagnostic->group] = ucfirst($diagnostic->group);
        }

        ksort($groups);

        return $groups;
    }

    /**
     * Get the selected diagnostic instances in execution order.
     *
     * @return list<Diagnostic>
     */
    protected function selected(?DiagnosticSelection $selection = null): array
    {
        $application = [];
        $packages = [];

        foreach ($this->diagnostics as $class) {
            $diagnostic = $this->instances[$class] ??= $this->container->make($class);

            if ($selection !== null && ! $selection->matches($diagnostic)) {
                continue;
            }

            if (DiagnosticSource::for($diagnostic)->application) {
                $application[] = $diagnostic;
            } else {
                $packages[] = $diagnostic;
            }
        }

        // Package diagnostics run first so application diagnostics may build on a verified foundation...
        return [...$packages, ...$application];
    }
}
