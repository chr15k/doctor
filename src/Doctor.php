<?php

namespace Laravel\Doctor;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
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
        return $this->runner($selection)->run();
    }

    /**
     * Begin a new diagnostic run.
     */
    public function runner(?DiagnosticSelection $selection = null): PendingRun
    {
        return new PendingRun($this, $selection ?? $this->defaultSelection());
    }

    /**
     * Get the diagnostic selection configured for the application.
     */
    public function defaultSelection(): DiagnosticSelection
    {
        return DiagnosticSelection::make(
            only: array_filter(config()->array('doctor.only', []), is_string(...)),
            except: array_filter(config()->array('doctor.except', []), is_string(...)),
        );
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

        if (! $outcome->fixable()) {
            throw new LogicException(sprintf('Diagnostic outcome [%s] is not fixable.', $outcome->result->code ?? $diagnostic::class));
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
        return collect($this->selected($selection))
            ->groupBy(static fn (Diagnostic $diagnostic): string => $diagnostic->group)
            ->map(static fn (Collection $diagnostics): array => array_values($diagnostics->all()))
            ->all();
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
