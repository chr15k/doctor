<?php

namespace Laravel\Doctor\Results;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\DiagnosticSource;

/**
 * @implements Arrayable<string, mixed>
 */
class DiagnosticOutcome implements Arrayable, JsonSerializable
{
    /**
     * Create a new diagnostic outcome instance.
     */
    public function __construct(
        public Diagnostic $diagnostic,
        public DiagnosticResult $result,
    ) {
        //
    }

    /**
     * Get the diagnostic's source metadata.
     */
    public function source(): DiagnosticSource
    {
        return DiagnosticSource::for($this->diagnostic);
    }

    /**
     * Determine whether this outcome may be fixed.
     */
    public function fixable(): bool
    {
        return $this->diagnostic instanceof Fixable
            && $this->result->status === Status::Fail
            && $this->result->fixable;
    }

    /**
     * Get the array representation of the outcome.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $source = $this->source();

        return [
            'class' => $this->diagnostic::class,
            'group' => $this->diagnostic->group,
            'name' => $this->diagnostic->name,
            'source' => [
                'label' => $source->label(),
                'package' => $source->package,
                'file' => $source->relativeFile,
                'application' => $source->application,
            ],
            'code' => $this->result->code,
            'status' => $this->result->status->value,
            'fixable' => $this->fixable(),
            'summary' => $this->result->summary,
            'details' => $this->result->details,
            'remediation' => $this->result->remediation,
            'links' => $this->result->links,
        ];
    }

    /**
     * Get the JSON representation of the outcome.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [...$this->toArray(), 'links' => (object) $this->result->links];
    }
}
