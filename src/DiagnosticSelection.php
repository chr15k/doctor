<?php

namespace Laravel\Doctor;

class DiagnosticSelection
{
    /**
     * Create a new diagnostic selection instance.
     *
     * @param  list<string>  $only
     * @param  list<string>  $except
     */
    public function __construct(
        public array $only = [],
        public array $except = [],
    ) {
        //
    }

    /**
     * Create a new normalized diagnostic selection.
     *
     * @param  iterable<string>  $only
     * @param  iterable<string>  $except
     */
    public static function make(iterable $only = [], iterable $except = []): self
    {
        return new self(
            only: self::normalize($only),
            except: self::normalize($except),
        );
    }

    /**
     * Determine whether a diagnostic matches the selection.
     */
    public function matches(Diagnostic $diagnostic): bool
    {
        if ($this->only !== [] && ! $this->matchesAny($this->only, $diagnostic)) {
            return false;
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
            || $criterion === $diagnostic->package();
    }
}
