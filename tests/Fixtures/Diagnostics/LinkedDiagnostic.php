<?php

namespace Laravel\Doctor\Tests\Fixtures\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;

class LinkedDiagnostic extends Diagnostic
{
    public string $name = 'Testing diagnostic has links';

    public string $group = 'testing';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'warning' => Message::make(
                summary: 'The linked diagnostic warned.',
                remediation: 'Follow the linked documentation.',
            )
                ->link(Link::docs('queues', 'connections-vs-queues'))
                ->link(Link::to('Related {topic} guide', 'https://example.com/{topic}')),
        ];
    }

    public function check(): DiagnosticResult
    {
        return $this->warn('warning', ['topic' => 'links'])
            ->withDetails('Detailed link context.');
    }
}
