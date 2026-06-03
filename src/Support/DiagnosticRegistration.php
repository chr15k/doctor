<?php

declare(strict_types=1);

namespace Laravel\Doctor\Support;

use Laravel\Doctor\Contracts\Diagnostic;

class DiagnosticRegistration
{
    /**
     * @param  class-string<Diagnostic>  $diagnostic
     */
    public function __construct(
        public readonly string $diagnostic,
    ) {
        //
    }

    public function package(): ?string
    {
        return DiagnosticSource::package($this->diagnostic);
    }

    public function source(): string
    {
        $package = $this->package();

        return $package === null
            ? 'internal'
            : 'package ['.$package.']';
    }
}
