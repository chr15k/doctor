<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\File;
use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\EnvironmentMode;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;

class EnvironmentFileIsGitIgnored extends Diagnostic implements Fixable
{
    public string $name = '.env is gitignored';

    public string $group = 'security';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'gitignore-missing' => Message::make(
                summary: 'The application does not have a .gitignore file.',
                remediation: 'Create a .gitignore file that includes .env so secrets are not committed.',
                confirmation: 'Create a .gitignore file that ignores .env?',
            )->link(Link::docs('configuration', 'environment-configuration')),
            'ignored' => 'Environment files are ignored by Git.',
            'not-ignored' => Message::make(
                summary: 'Environment files are not ignored by Git.',
                remediation: 'Add .env to .gitignore so secrets are not committed.',
                confirmation: 'Add .env to .gitignore?',
            )->link(Link::docs('configuration', 'environment-configuration')),
            'already-ignored' => 'Environment files are already ignored by Git.',
            'update-failed' => 'The .gitignore file could not be updated.',
            'updated' => '.env was added to .gitignore.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! File::exists($this->gitignorePath())) {
            return $this->fail('gitignore-missing')->fixable(EnvironmentMode::Local);
        }

        if ($this->ignores('.env')) {
            return $this->pass('ignored');
        }

        return $this->fail('not-ignored')->fixable(EnvironmentMode::Local);
    }

    /**
     * Fix the diagnostic.
     */
    public function fix(DiagnosticResult $result): FixResult
    {
        $path = $this->gitignorePath();

        if (File::exists($path) && $this->ignores('.env')) {
            return $this->fixed('already-ignored');
        }

        $contents = File::exists($path) ? File::get($path) : '';
        $prefix = $contents !== '' && ! str_ends_with($contents, PHP_EOL) ? PHP_EOL : '';

        if (File::put($path, $contents.$prefix.'.env'.PHP_EOL) === false) {
            return $this->fixFailed('update-failed');
        }

        return $this->fixed('updated');
    }

    /**
     * Determine whether .gitignore ignores the path.
     */
    private function ignores(string $path): bool
    {
        $ignored = false;

        foreach ($this->patterns() as $pattern) {
            if (str_starts_with($pattern, '!')) {
                if (fnmatch(substr($pattern, 1), $path)) {
                    $ignored = false;
                }
            } elseif (fnmatch($pattern, $path)) {
                $ignored = true;
            }
        }

        return $ignored;
    }

    /**
     * Get normalized .gitignore patterns.
     *
     * @return iterable<string>
     */
    private function patterns(): iterable
    {
        return File::lines($this->gitignorePath())
            ->map(fn (string $line): string => trim($line))
            ->reject(fn (string $line): bool => $line === '' || str_starts_with($line, '#'))
            ->map(fn (string $line): string => ltrim($line, '/'));
    }

    /**
     * Get the application .gitignore path.
     */
    private function gitignorePath(): string
    {
        return base_path('.gitignore');
    }
}
