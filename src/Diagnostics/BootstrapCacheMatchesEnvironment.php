<?php

namespace Laravel\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;

class BootstrapCacheMatchesEnvironment extends Diagnostic
{
    public string $name = 'Bootstrap cache matches environment';

    public string $group = 'configuration';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $cached = $this->cachedFiles();

        if ($cached === []) {
            if (! app()->environment(['local', 'testing'])) {
                return DiagnosticResult::warn('Laravel bootstrap files are not cached.')
                    ->suggest('Run `php artisan optimize` or `php artisan config:cache` during deployment.');
            }

            return DiagnosticResult::pass('Laravel bootstrap files are not cached.');
        }

        if (! app()->environment(['local', 'testing'])) {
            return DiagnosticResult::pass('Laravel bootstrap files are cached.');
        }

        return DiagnosticResult::notice(sprintf('%s %s cached.', ucfirst($this->formatCachedFiles($cached)), count($cached) === 1 ? 'is' : 'are'))
            ->suggest('If recent changes are not appearing, run `php artisan optimize:clear`.');
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
        if (count($cached) === 1) {
            return $cached[0];
        }

        if (count($cached) === 2) {
            return implode(' and ', $cached);
        }

        $last = array_pop($cached);

        return implode(', ', $cached).', and '.$last;
    }
}
