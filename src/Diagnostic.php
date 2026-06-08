<?php

namespace Laravel\Doctor;

use Illuminate\Support\Str;
use Laravel\Doctor\Results\DiagnosticResult;

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
