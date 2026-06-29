<?php

namespace Laravel\Doctor;

class DiagnosticSelection
{
    /**
     * The normalized selectors diagnostics must match.
     *
     * @var list<string>
     */
    public array $only = [];

    /**
     * The normalized selectors diagnostics must not match.
     *
     * @var list<string>
     */
    public array $except = [];

    /**
     * The normalized only constraints that must all match.
     *
     * @var list<list<string>>
     */
    protected array $onlyConstraints = [];

    /**
     * Create a new diagnostic selection instance.
     *
     * @param  iterable<string>  $only
     * @param  iterable<string>  $except
     * @param  list<iterable<string>>  $onlyConstraints
     */
    public function __construct(
        iterable $only = [],
        iterable $except = [],
        array $onlyConstraints = [],
    ) {
        $this->only = self::normalize($only);
        $this->except = self::normalize($except);

        foreach ($onlyConstraints as $constraint) {
            $normalized = self::normalize($constraint);

            if ($normalized !== []) {
                $this->onlyConstraints[] = $normalized;
            }
        }

        if ($this->onlyConstraints === [] && $this->only !== []) {
            $this->onlyConstraints[] = $this->only;
        }
    }

    /**
     * Create a new normalized diagnostic selection.
     *
     * @param  iterable<string>  $only
     * @param  iterable<string>  $except
     */
    public static function make(iterable $only = [], iterable $except = []): self
    {
        return new self(only: $only, except: $except);
    }

    /**
     * Add another selection constraint.
     *
     * @param  iterable<string>  $only
     * @param  iterable<string>  $except
     */
    public function constrain(iterable $only = [], iterable $except = []): self
    {
        $only = self::normalize($only);
        $except = self::normalize($except);

        return new self(
            only: $only !== [] ? $only : $this->only,
            except: [...$this->except, ...$except],
            onlyConstraints: [
                ...$this->onlyConstraints,
                ...($only !== [] ? [$only] : []),
            ],
        );
    }

    /**
     * Determine whether a diagnostic matches the selection.
     */
    public function matches(Diagnostic $diagnostic): bool
    {
        foreach ($this->onlyConstraints as $criteria) {
            if (! $this->matchesAny($criteria, $diagnostic)) {
                return false;
            }
        }

        if ($this->except !== [] && $this->matchesAny($this->except, $diagnostic)) {
            return false;
        }

        return true;
    }

    /**
     * Normalize selection values.
     *
     * @param  iterable<string>  $values
     * @return list<string>
     */
    protected static function normalize(iterable $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            foreach (explode(',', $value) as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $normalized[] = $part;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Determine whether any criterion matches the diagnostic.
     *
     * @param  list<string>  $criteria
     */
    protected function matchesAny(array $criteria, Diagnostic $diagnostic): bool
    {
        foreach ($criteria as $criterion) {
            if ($this->matchesCriterion($criterion, $diagnostic)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether a criterion matches the diagnostic.
     */
    protected function matchesCriterion(string $criterion, Diagnostic $diagnostic): bool
    {
        return $criterion === $diagnostic::class
            || $criterion === class_basename($diagnostic)
            || $criterion === $diagnostic->group
            || $this->matchesPackage($criterion, $diagnostic);
    }

    /**
     * Determine whether a criterion matches one of the diagnostic's package selectors.
     */
    protected function matchesPackage(string $criterion, Diagnostic $diagnostic): bool
    {
        foreach ($this->packages($diagnostic) as $package) {
            if ($criterion === $package) {
                return true;
            }

            if (str_ends_with($criterion, '/*') && str_starts_with($package, substr($criterion, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the Composer package selectors for the diagnostic.
     *
     * @return list<string>
     */
    protected function packages(Diagnostic $diagnostic): array
    {
        $package = $diagnostic->package();

        return $package !== null ? [$package] : [];
    }
}
