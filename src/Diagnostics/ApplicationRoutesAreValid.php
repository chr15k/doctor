<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;

class ApplicationRoutesAreValid extends Diagnostic
{
    public string $name = 'Application routes';

    public string $group = 'application';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'valid' => 'The application routes are valid.',
            'invalid' => Message::make(
                summary: 'The application routes could not be loaded.',
                remediation: 'Check your route definitions and referenced controllers.',
            )->link(Link::docs('routing')),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $process = Process::path(base_path())->run([
            PHP_BINARY,
            'artisan',
            'route:list',
            '--no-ansi',
            '--no-interaction',
        ]);

        if ($process->successful()) {
            return $this->pass('valid');
        }

        return $this->fail('invalid')
            ->withDetails($this->extractExceptionMessage(
                $process->output().PHP_EOL.$process->errorOutput(),
            ));
    }

    private function extractExceptionMessage(string $output): string
    {
        if (preg_match(
            '/^\s*([A-Za-z\\\\]+Exception)\s*\R+\s*(.+?)(?=\s*\R+\s*at\s)/ms',
            $output,
            $matches,
        )) {
            return sprintf('%s: %s', $matches[1], trim($matches[2]));
        }

        return 'The route:list command failed.';
    }
}