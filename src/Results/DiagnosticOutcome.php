<?php

namespace Laravel\Doctor\Results;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
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
