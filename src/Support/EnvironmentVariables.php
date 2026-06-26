<?php

namespace Laravel\Doctor\Support;

use Illuminate\Support\Facades\File;

class EnvironmentVariables
{
    /**
     * The variables declared in environment files.
     *
     * @var array<string, true>|null
     */
    private ?array $fileVariables = null;

    /**
     * Determine whether an environment variable is available to Laravel.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $_ENV)
            || array_key_exists($key, $_SERVER)
            || getenv($key) !== false
            || isset($this->fileVariables()[$key]);
    }

    /**
     * Get variables declared by the application's environment files.
     *
     * @return array<string, true>
     */
    private function fileVariables(): array
    {
        if ($this->fileVariables !== null) {
            return $this->fileVariables;
        }

        $variables = [];

        foreach ($this->environmentFiles() as $file) {
            foreach (File::lines($file) as $line) {
                if (! is_string($line)) {
                    continue;
                }

                $key = $this->keyFromLine($line);

                if ($key !== null) {
                    $variables[$key] = true;
                }
            }
        }

        return $this->fileVariables = $variables;
    }

    /**
     * Get the application's environment files.
     *
     * @return list<string>
     */
    private function environmentFiles(): array
    {
        $files = File::glob(base_path('.env*')) ?: [];

        return array_values(array_filter(
            $files,
            static fn (string $file): bool => is_file($file)
                && basename($file) !== '.env.example'
                && ! str_ends_with(basename($file), '.example'),
        ));
    }

    /**
     * Extract an environment variable key from a dotenv line.
     */
    private function keyFromLine(string $line): ?string
    {
        if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
