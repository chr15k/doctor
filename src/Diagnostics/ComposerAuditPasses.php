<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Composer;
use Laravel\Doctor\Support\Details;

class ComposerAuditPasses extends Diagnostic
{
    public string $name = 'Composer audit passes';

    public string $group = 'security';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'lock-missing' => 'The application does not have a composer.lock file.',
            'vulnerable' => Message::make(
                summary: 'Composer audit found vulnerable dependencies.',
                remediation: 'Update or replace the vulnerable packages reported by `composer audit`.',
            ),
            'audit-failed' => Message::make(
                summary: 'Composer audit could not be completed.',
                remediation: 'Run `composer audit` locally and resolve the reported issue.',
            ),
            'clean' => 'Composer audit did not find vulnerable dependencies.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! is_file(base_path('composer.lock'))) {
            return $this->skip('lock-missing');
        }

        $process = Process::path(base_path())->timeout(10)->run([
            ...Composer::command(),
            'audit',
            '--format=json',
            '--no-interaction',
        ]);

        $advisories = $this->advisoryCount($process->output());

        if ($advisories > 0) {
            return $this->fail('vulnerable')
                ->withDetails(sprintf(
                    '%d security %s %s reported.',
                    $advisories,
                    Str::plural('advisory', $advisories),
                    $advisories === 1 ? 'was' : 'were',
                ));
        }

        if (! $process->successful()) {
            return $this->fail('audit-failed')
                ->withDetails(Details::processOutput(
                    $process->output(),
                    $process->errorOutput(),
                    'Composer exited without audit details.',
                ));
        }

        return $this->pass('clean');
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
