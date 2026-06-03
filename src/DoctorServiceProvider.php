<?php

declare(strict_types=1);

namespace Laravel\Doctor;

use Illuminate\Support\ServiceProvider;
use Laravel\Doctor\Contracts\ProcessRunner;
use Laravel\Doctor\Diagnostics\ApplicationKeyIsSet;
use Laravel\Doctor\Diagnostics\ComposerAutoloadIsValid;
use Laravel\Doctor\Diagnostics\ComposerLockIsFresh;
use Laravel\Doctor\Diagnostics\ConfigurationFilesLoad;
use Laravel\Doctor\Diagnostics\DatabaseConnectionIsAvailable;
use Laravel\Doctor\Diagnostics\MissingEnvironmentFile;
use Laravel\Doctor\Diagnostics\PendingMigrations;
use Laravel\Doctor\Diagnostics\PublicStorageLinkExists;
use Laravel\Doctor\Diagnostics\RequiredPhpExtensionsAreInstalled;
use Laravel\Doctor\Diagnostics\VendorAutoloadExists;
use Laravel\Doctor\Diagnostics\WritableStorage;
use Laravel\Doctor\Support\DiagnosticRegistry;
use Laravel\Doctor\Support\DiagnosticRunner;
use Laravel\Doctor\Support\Process\NativeProcessRunner;

class DoctorServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->app->singleton(DiagnosticRegistry::class, function (): DiagnosticRegistry {
            return (new DiagnosticRegistry)
                ->diagnostic(MissingEnvironmentFile::class)
                ->diagnostic(ApplicationKeyIsSet::class)
                ->diagnostic(RequiredPhpExtensionsAreInstalled::class)
                ->diagnostic(VendorAutoloadExists::class)
                ->diagnostic(ComposerAutoloadIsValid::class)
                ->diagnostic(ComposerLockIsFresh::class)
                ->diagnostic(ConfigurationFilesLoad::class)
                ->diagnostic(DatabaseConnectionIsAvailable::class)
                ->diagnostic(PendingMigrations::class)
                ->diagnostic(PublicStorageLinkExists::class)
                ->diagnostic(WritableStorage::class);
        });

        $this->app->singleton(ProcessRunner::class, NativeProcessRunner::class);

        $this->app->singleton(DiagnosticRunner::class);
        $this->app->singleton(Doctor::class);
    }

    /**
     * Bootstrap the package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DoctorCommand::class,
            ]);
        }
    }
}
