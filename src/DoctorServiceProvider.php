<?php

namespace Laravel\Doctor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Doctor\Console\DoctorCommand;
use Laravel\Doctor\Diagnostics\ApplicationKeyIsSet;
use Laravel\Doctor\Diagnostics\AsynchronousQueueIsConfigured;
use Laravel\Doctor\Diagnostics\BootstrapCacheMatchesEnvironment;
use Laravel\Doctor\Diagnostics\ComposerAutoloadIsValid;
use Laravel\Doctor\Diagnostics\ComposerLockIsFresh;
use Laravel\Doctor\Diagnostics\ConfigurationFilesLoad;
use Laravel\Doctor\Diagnostics\DatabaseConnectionIsAvailable;
use Laravel\Doctor\Diagnostics\EnvironmentFileExists;
use Laravel\Doctor\Diagnostics\MigrationsAreUpToDate;
use Laravel\Doctor\Diagnostics\PublicStorageLinkExists;
use Laravel\Doctor\Diagnostics\RequiredPhpExtensionsAreInstalled;
use Laravel\Doctor\Diagnostics\SqliteDatabaseExists;
use Laravel\Doctor\Diagnostics\StorageIsWritable;
use Laravel\Doctor\Diagnostics\VendorAutoloadExists;

class DoctorServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->app->singleton(Doctor::class, function (Application $app): Doctor {
            return (new Doctor($app))->diagnostics([
                EnvironmentFileExists::class,
                ApplicationKeyIsSet::class,
                RequiredPhpExtensionsAreInstalled::class,
                VendorAutoloadExists::class,
                ComposerAutoloadIsValid::class,
                ComposerLockIsFresh::class,
                ConfigurationFilesLoad::class,
                BootstrapCacheMatchesEnvironment::class,
                DatabaseConnectionIsAvailable::class,
                SqliteDatabaseExists::class,
                MigrationsAreUpToDate::class,
                AsynchronousQueueIsConfigured::class,
                PublicStorageLinkExists::class,
                StorageIsWritable::class,
            ]);
        });
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
