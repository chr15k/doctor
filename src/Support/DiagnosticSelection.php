<?php

declare(strict_types=1);

namespace Laravel\Doctor\Support;

use Laravel\Doctor\DiagnosticMetadata;

class DiagnosticSelection
{
    /**
     * @param  list<string>  $only
     * @param  list<string>  $except
     */
    public function __construct(
        public readonly array $only = [],
        public readonly array $except = [],
    ) {
        //
    }

    /**
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

    public function matches(DiagnosticMetadata $definition, DiagnosticRegistration $registration): bool
    {
        if ($this->only !== [] && ! $this->matchesAny($this->only, $definition, $registration)) {
            return false;
        }

        if ($this->except !== [] && $this->matchesAny($this->except, $definition, $registration)) {
            return false;
        }

        return true;
    }

    /**
     * @param  iterable<string>  $values
     * @return list<string>
     */
    private static function normalize(iterable $values): array
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
     * @param  list<string>  $criteria
     */
    private function matchesAny(array $criteria, DiagnosticMetadata $definition, DiagnosticRegistration $registration): bool
    {
        foreach ($criteria as $criterion) {
            if ($this->matchesCriterion($criterion, $definition, $registration)) {
                return true;
            }
        }

        return false;
    }

    private function matchesCriterion(string $criterion, DiagnosticMetadata $definition, DiagnosticRegistration $registration): bool
    {
        return $criterion === $registration->diagnostic
            || $criterion === $this->shortClassName($registration->diagnostic)
            || $criterion === $definition->group
            || $criterion === $registration->package();
    }

    private function shortClassName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
