<?php

namespace Laravel\Doctor;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixOutcome;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Outcome;
use Stringable;

abstract class Diagnostic
{
    public string $name;

    public string $group;

    /**
     * Create a new diagnostic instance.
     */
    public function __construct()
    {
        $this->name ??= Str::headline(class_basename(static::class));
        $this->group ??= Str::kebab(class_basename(static::class));
    }

    /**
     * Run the diagnostic.
     */
    abstract public function check(): DiagnosticResult;

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [];
    }

    /**
     * Get the diagnostic's named fix outcome definitions.
     *
     * @return array<string, FixOutcome>
     */
    protected function fixOutcomes(): array
    {
        return [];
    }

    /**
     * Create a diagnostic result from a named outcome.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function result(string $outcome, array $replace = []): DiagnosticResult
    {
        $definition = $this->outcome($outcome);
        $tokens = $this->tokens($replace);

        $result = new DiagnosticResult(
            $definition->status,
            Str::swap($tokens, $definition->summary),
            $this->code($outcome),
        );

        if ($definition->remediation !== null) {
            $result->suggest(Str::swap($tokens, $definition->remediation));
        }

        if ($definition->confirmation !== null) {
            $result->confirmUsing(Str::swap($tokens, $definition->confirmation));
        }

        foreach ($definition->links as $label => $url) {
            $result->link(Str::swap($tokens, $label), Str::swap($tokens, $url));
        }

        return $result;
    }

    /**
     * Create a fix result from a named fix outcome.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function fixResult(string $outcome, array $replace = []): FixResult
    {
        $definition = $this->fixOutcome($outcome);

        return new FixResult(
            $definition->status,
            Str::swap($this->tokens($replace), $definition->summary),
            $this->fixCode($outcome),
        );
    }

    /**
     * Get a named outcome definition.
     */
    protected function outcome(string $outcome): Outcome
    {
        $definition = $this->outcomes()[$outcome] ?? null;

        if (! $definition instanceof Outcome) {
            throw new InvalidArgumentException(sprintf(
                'Outcome [%s] is not defined for diagnostic [%s].',
                $outcome,
                static::class,
            ));
        }

        return $definition;
    }

    /**
     * Get a named fix outcome definition.
     */
    protected function fixOutcome(string $outcome): FixOutcome
    {
        $definition = $this->fixOutcomes()[$outcome] ?? null;

        if (! $definition instanceof FixOutcome) {
            throw new InvalidArgumentException(sprintf(
                'Fix outcome [%s] is not defined for diagnostic [%s].',
                $outcome,
                static::class,
            ));
        }

        return $definition;
    }

    /**
     * Get a machine-readable result code for the outcome.
     */
    protected function code(string $outcome): string
    {
        return Str::of(class_basename(static::class))
            ->kebab()
            ->append('.'.$outcome)
            ->toString();
    }

    /**
     * Get a machine-readable fix result code for the outcome.
     */
    protected function fixCode(string $outcome): string
    {
        return Str::of(class_basename(static::class))
            ->kebab()
            ->append('.fix.'.$outcome)
            ->toString();
    }

    /**
     * Normalize replacement values into Str::swap tokens.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     * @return array<string, string>
     */
    protected function tokens(array $replace): array
    {
        $tokens = [];

        foreach ($replace as $key => $value) {
            $tokens['{'.$key.'}'] = match (true) {
                $value === null => '',
                is_bool($value) => $value ? 'true' : 'false',
                default => (string) $value,
            };
        }

        return $tokens;
    }

    /**
     * Get the Composer package that provides the diagnostic.
     */
    public function package(): ?string
    {
        return DiagnosticSource::package(static::class);
    }

    /**
     * Get the user-facing diagnostic source label.
     */
    public function source(): string
    {
        return DiagnosticSource::source(static::class);
    }
}
