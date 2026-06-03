<?php

declare(strict_types=1);

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
use Laravel\Doctor\DoctorServiceProvider;
use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Support\DiagnosticRegistry;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PassingDiagnostic;

it('loads the package service provider', function (): void {
    expect($this->app->getProvider(DoctorServiceProvider::class))->not->toBeNull();
});

it('binds the doctor service and facade', function (): void {
    Doctor::diagnostic(PassingDiagnostic::class);

    expect($this->app->make(DiagnosticRegistry::class)->diagnosticClasses())
        ->toBe([
            MissingEnvironmentFile::class,
            ApplicationKeyIsSet::class,
            RequiredPhpExtensionsAreInstalled::class,
            VendorAutoloadExists::class,
            ComposerAutoloadIsValid::class,
            ComposerLockIsFresh::class,
            ConfigurationFilesLoad::class,
            DatabaseConnectionIsAvailable::class,
            PendingMigrations::class,
            PublicStorageLinkExists::class,
            WritableStorage::class,
            PassingDiagnostic::class,
        ]);
});

it('registers the default diagnostics', function (): void {
    expect($this->app->make(DiagnosticRegistry::class)->diagnosticClasses())
        ->toBe([
            MissingEnvironmentFile::class,
            ApplicationKeyIsSet::class,
            RequiredPhpExtensionsAreInstalled::class,
            VendorAutoloadExists::class,
            ComposerAutoloadIsValid::class,
            ComposerLockIsFresh::class,
            ConfigurationFilesLoad::class,
            DatabaseConnectionIsAvailable::class,
            PendingMigrations::class,
            PublicStorageLinkExists::class,
            WritableStorage::class,
        ]);
});
