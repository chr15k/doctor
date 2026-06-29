<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Str;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Outcome;

class BootstrapCacheMatchesEnvironment extends Diagnostic
{
    public string $name = 'Bootstrap cache matches environment';

    public string $group = 'configuration';

    /**
     * Get the diagnostic's named outcome definitions.
     *
     * @return array<string, Outcome>
     */
    protected function outcomes(): array
    {
        return [
            'uncached-production' => Outcome::warn(
                summary: 'Laravel bootstrap files are not cached.',
                remediation: 'Run `php artisan optimize` or `php artisan config:cache` during deployment.',
            ),
            'uncached-local' => Outcome::pass('Laravel bootstrap files are not cached.'),
            'cached-production' => Outcome::pass('Laravel bootstrap files are cached.'),
            'cached-local' => Outcome::notice(
                summary: 'Cached bootstrap {files} detected: {cached}.',
                remediation: 'If recent changes are not appearing, run `php artisan optimize:clear`.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $cached = $this->cachedFiles();

        if ($cached === []) {
            if (! app()->environment(['local', 'testing'])) {
                return $this->result('uncached-production');
            }

            return $this->result('uncached-local');
        }

        if (! app()->environment(['local', 'testing'])) {
            return $this->result('cached-production');
        }

        return $this->result('cached-local', [
            'files' => Str::plural('file', count($cached)),
            'cached' => $this->formatCachedFiles($cached),
        ]);
    }

    /**
     * @return list<string>
     */
    private function cachedFiles(): array
    {
        $cached = [];

        if (is_file(app()->getCachedConfigPath())) {
            $cached[] = 'config';
        }

        if (is_file(app()->getCachedEventsPath())) {
            $cached[] = 'events';
        }

        if (is_file(app()->getCachedRoutesPath())) {
            $cached[] = 'routes';
        }

        if ($this->viewsAreCached()) {
            $cached[] = 'views';
        }

        return $cached;
    }

    private function viewsAreCached(): bool
    {
        $path = config('view.compiled');

        if (! is_string($path)) {
            return false;
        }

        $files = glob($path.'/*.php');

        return is_array($files) && $files !== [];
    }

    /**
     * @param  list<string>  $cached
     */
    private function formatCachedFiles(array $cached): string
    {
        return collect($cached)->join(', ', ' and ');
    }
}
