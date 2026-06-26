<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use LogicException;
use RuntimeException;
use Throwable;

class ConfigurationCanBeCached extends Diagnostic
{
    public string $name = 'Configuration can be cached';

    public string $group = 'configuration';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        try {
            $this->cacheConfiguration();
        } catch (Throwable $e) {
            return DiagnosticResult::fail('Laravel configuration cannot be cached.')
                ->withDetails($e->getMessage())
                ->suggest('Remove non-serializable values from configuration files before running `php artisan config:cache`.');
        }

        return DiagnosticResult::pass('Laravel configuration can be cached.');
    }

    /**
     * Cache the current configuration to a temporary file.
     */
    private function cacheConfiguration(): void
    {
        $path = $this->temporaryCachePath();

        try {
            File::put($path, '<?php return '.var_export(config()->all(), true).';'.PHP_EOL);

            require $path;
        } catch (Throwable $e) {
            throw $this->serializationException($e);
        } finally {
            File::delete($path);
        }
    }

    /**
     * Build the most specific serialization exception available.
     */
    private function serializationException(Throwable $previous): Throwable
    {
        foreach (Arr::dot(config()->all()) as $key => $value) {
            try {
                eval(var_export($value, true).';');
            } catch (Throwable $e) {
                return new LogicException(
                    sprintf('The configuration value at [%s] is not serializable.', $key),
                    0,
                    $e,
                );
            }
        }

        return new LogicException('The configuration files are not serializable.', 0, $previous);
    }

    /**
     * Get a temporary config cache path.
     */
    private function temporaryCachePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel-doctor-config-');

        if ($path === false) {
            throw new RuntimeException('Doctor could not create a temporary config cache file.');
        }

        return $path;
    }
}
