<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class ComposerDependenciesAreAudited extends Diagnostic
{
    public string $name = 'Composer dependencies are audited';

    public string $group = 'security';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! is_file(base_path('composer.lock'))) {
            return DiagnosticResult::skip('The application does not have a composer.lock file.');
        }

        $process = Process::path(base_path())->timeout(10)->run([
            'composer',
            'audit',
            '--format=json',
            '--no-interaction',
        ]);

        $advisories = $this->advisoryCount($process->output());

        if ($advisories > 0) {
            return DiagnosticResult::fail('Composer audit found vulnerable dependencies.')
                ->withDetails(sprintf('%d security %s reported.', $advisories, $advisories === 1 ? 'advisory was' : 'advisories were'))
                ->suggest('Run `composer audit` and update or replace vulnerable dependencies.');
        }

        if (! $process->successful()) {
            return DiagnosticResult::fail('Composer audit could not be completed.')
                ->withDetails($this->processOutput($process->output(), $process->errorOutput()))
                ->suggest('Run `composer audit` locally and resolve the reported issue.');
        }

        return DiagnosticResult::pass('Composer audit did not find vulnerable dependencies.');
    }

    /**
     * Count advisories from Composer audit JSON output.
     */
    private function advisoryCount(string $output): int
    {
        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return 0;
        }

        $advisories = $decoded['advisories'] ?? [];

        if (! is_array($advisories)) {
            return 0;
        }

        $count = 0;

        foreach ($advisories as $packageAdvisories) {
            if (is_array($packageAdvisories)) {
                $count += count($packageAdvisories);
            }
        }

        return $count;
    }

    /**
     * Get the most useful process output.
     */
    private function processOutput(string $output, string $errorOutput): string
    {
        $output = trim($errorOutput !== '' ? $errorOutput : $output);

        return $output === '' ? 'Composer exited without audit details.' : $output;
    }
}
