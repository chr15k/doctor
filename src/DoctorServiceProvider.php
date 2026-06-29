<?php

namespace Laravel\Doctor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Doctor\Console\DoctorCommand;
use Laravel\Doctor\Diagnostics\ApplicationKeyIsSet;
use Laravel\Doctor\Diagnostics\ApplicationTimezoneIsValid;
use Laravel\Doctor\Diagnostics\AsynchronousQueueIsConfigured;
use Laravel\Doctor\Diagnostics\BootstrapCacheMatchesEnvironment;
use Laravel\Doctor\Diagnostics\CacheStoreIsReachable;
use Laravel\Doctor\Diagnostics\ComposerAutoloadIsValid;
use Laravel\Doctor\Diagnostics\ComposerDependenciesAreAudited;
use Laravel\Doctor\Diagnostics\ComposerLockIsFresh;
use Laravel\Doctor\Diagnostics\ConfigurationCanBeCached;
use Laravel\Doctor\Diagnostics\ConfigurationEnvironmentVariablesExist;
use Laravel\Doctor\Diagnostics\ConfigurationFilesLoad;
use Laravel\Doctor\Diagnostics\DatabaseConnectionIsAvailable;
use Laravel\Doctor\Diagnostics\DatabaseTimezoneMatchesApplication;
use Laravel\Doctor\Diagnostics\DebugModeMatchesEnvironment;
use Laravel\Doctor\Diagnostics\EnvironmentFileExists;
use Laravel\Doctor\Diagnostics\EnvironmentFileIsIgnored;
use Laravel\Doctor\Diagnostics\FilesystemDisksAreReachable;
use Laravel\Doctor\Diagnostics\MigrationsAreUpToDate;
use Laravel\Doctor\Diagnostics\PhpVersionMatchesComposerRequirement;
use Laravel\Doctor\Diagnostics\PublicStorageLinkExists;
use Laravel\Doctor\Diagnostics\QueueConnectionIsReachable;
use Laravel\Doctor\Diagnostics\RecommendedPhpExtensionsAreInstalled;
use Laravel\Doctor\Diagnostics\RedisConnectionsAreReachable;
use Laravel\Doctor\Diagnostics\RequiredPhpExtensionsAreInstalled;
use Laravel\Doctor\Diagnostics\ScheduledTasksNeedScheduler;
use Laravel\Doctor\Diagnostics\SessionDriverIsReachable;
use Laravel\Doctor\Diagnostics\SqliteDatabaseExists;
use Laravel\Doctor\Diagnostics\StorageIsWritable;

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
                PhpVersionMatchesComposerRequirement::class,
                RequiredPhpExtensionsAreInstalled::class,
                RecommendedPhpExtensionsAreInstalled::class,
                ApplicationTimezoneIsValid::class,
                ComposerAutoloadIsValid::class,
                ComposerLockIsFresh::class,
                ConfigurationFilesLoad::class,
                ConfigurationCanBeCached::class,
                ConfigurationEnvironmentVariablesExist::class,
                BootstrapCacheMatchesEnvironment::class,
                DatabaseConnectionIsAvailable::class,
                DatabaseTimezoneMatchesApplication::class,
                SqliteDatabaseExists::class,
                MigrationsAreUpToDate::class,
                CacheStoreIsReachable::class,
                RedisConnectionsAreReachable::class,
                QueueConnectionIsReachable::class,
                AsynchronousQueueIsConfigured::class,
                SessionDriverIsReachable::class,
                ScheduledTasksNeedScheduler::class,
                PublicStorageLinkExists::class,
                FilesystemDisksAreReachable::class,
                StorageIsWritable::class,
                DebugModeMatchesEnvironment::class,
                EnvironmentFileIsIgnored::class,
                ComposerDependenciesAreAudited::class,
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
