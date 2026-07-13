<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\File;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;

class EnvironmentFileIsGitIgnored extends Diagnostic
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
            ),
            'ignored' => 'Environment files are ignored by Git.',
            'not-ignored' => Message::make(
                summary: 'Environment files are not ignored by Git.',
                remediation: 'Add .env to .gitignore so secrets are not committed.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (! File::exists($this->gitignorePath())) {
            return $this->fail('gitignore-missing');
        }

        if ($this->ignores('.env')) {
            return $this->pass('ignored');
        }

        return $this->fail('not-ignored');
    }

    /**
     * Determine whether .gitignore ignores the path.
     */
    private function ignores(string $path): bool
    {
        $ignored = false;

        foreach ($this->patterns() as $pattern) {
            if ($this->isNegated($pattern) && $this->matches(substr($pattern, 1), $path)) {
                $ignored = false;

                continue;
            }

            if (! $this->isNegated($pattern) && $this->matches($pattern, $path)) {
                $ignored = true;
            }
        }

        return $ignored;
    }

    /**
     * Get normalized .gitignore patterns.
     *
     * @return list<string>
     */
    private function patterns(): array
    {
        $patterns = [];

        foreach (File::lines($this->gitignorePath()) as $line) {
            if (! is_string($line)) {
                continue;
            }

            $pattern = $this->normalizePattern($line);

            if ($pattern !== '') {
                $patterns[] = $pattern;
            }
        }

        return $patterns;
    }

    /**
     * Normalize a .gitignore pattern line.
     */
    private function normalizePattern(string $pattern): string
    {
        $pattern = trim($pattern);

        if ($pattern === '' || str_starts_with($pattern, '#')) {
            return '';
        }

        return ltrim($pattern, '/');
    }

    /**
     * Determine whether a .gitignore pattern is negated.
     */
    private function isNegated(string $pattern): bool
    {
        return str_starts_with($pattern, '!');
    }

    /**
     * Determine whether a pattern matches a path.
     */
    private function matches(string $pattern, string $path): bool
    {
        return fnmatch($pattern, $path) || fnmatch($pattern, basename($path));
    }

    /**
     * Get the application .gitignore path.
     */
    private function gitignorePath(): string
    {
        return base_path('.gitignore');
    }
}
