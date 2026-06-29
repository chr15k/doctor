<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;
use Laravel\Doctor\Support\Details;

class ComposerAuditPasses extends Diagnostic
{
    public string $name = 'Composer audit passes';

    public string $group = 'security';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'lock-missing' => Outcome::skip('The application does not have a composer.lock file.'),
            'vulnerable' => Outcome::fail(
                summary: 'Composer audit found vulnerable dependencies.',
                remediation: 'Run `composer audit` and update or replace vulnerable dependencies.',
            ),
            'audit-failed' => Outcome::fail(
                summary: 'Composer audit could not be completed.',
                remediation: 'Run `composer audit` locally and resolve the reported issue.',
            ),
            'clean' => Outcome::pass('Composer audit did not find vulnerable dependencies.'),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! is_file(base_path('composer.lock'))) {
            return $this->result('lock-missing');
        }

        $process = Process::path(base_path())->timeout(10)->run([
            'composer',
            'audit',
            '--format=json',
            '--no-interaction',
        ]);

        $advisories = $this->advisoryCount($process->output());

        if ($advisories > 0) {
            return $this->result('vulnerable')
                ->withDetails(sprintf(
                    '%d security %s %s reported.',
                    $advisories,
                    Str::plural('advisory', $advisories),
                    $advisories === 1 ? 'was' : 'were',
                ));
        }

        if (! $process->successful()) {
            return $this->result('audit-failed')
                ->withDetails(Details::processOutput(
                    $process->output(),
                    $process->errorOutput(),
                    'Composer exited without audit details.',
                ));
        }

        return $this->result('clean');
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
}
