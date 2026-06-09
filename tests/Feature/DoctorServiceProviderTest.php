<?php

use Illuminate\Support\Facades\Process;
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
use Laravel\Doctor\DoctorServiceProvider;
use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\LinkedDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PassingDiagnostic;

it('loads the package service provider', function (): void {
    expect($this->app->getProvider(DoctorServiceProvider::class))->not->toBeNull();
});

it('does not redefine Symfony console verbosity options', function (): void {
    $command = new DoctorCommand;

    expect($command->getDefinition()->hasOption('verbose'))->toBeFalse()
        ->and($command->getDefinition()->hasOption('format'))->toBeTrue();
});

it('does not repeat diagnostics that were fixed', function (): void {
    config(['app.key' => '']);

    Process::fake([
        '*' => Process::result(output: 'Application key set successfully.'),
    ]);

    $this->artisan('doctor --only=ApplicationKeyIsSet --fix')
        ->expectsOutputToContain('[pass] fix Application key is set (doctor): The application key was generated.')
        ->doesntExpectOutputToContain('[fail] Application key is set (doctor): Laravel does not have an application key.')
        ->assertExitCode(0);
});

it('renders notice diagnostics without diagnostic source noise', function (): void {
    $basePath = sys_get_temp_dir().'/laravel-doctor-notice-output-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath.'/bootstrap/cache', 0775, true);
    mkdir($basePath.'/storage/framework/views', 0775, true);

    $this->app->setBasePath($basePath);
    $this->app->useStoragePath($basePath.'/storage');
    config(['app.env' => 'local']);
    config(['view.compiled' => $basePath.'/storage/framework/views']);

    file_put_contents($this->app->getCachedEventsPath(), '<?php return [];');
    file_put_contents($basePath.'/storage/framework/views/example.php', '<?php echo "cached";');

    $this->artisan('doctor --only=BootstrapCacheMatchesEnvironment')
        ->expectsOutputToContain('Notes:')
        ->expectsOutputToContain('- Events and views are cached. If recent changes are not appearing, run `php artisan optimize:clear`.')
        ->doesntExpectOutputToContain('[notice]')
        ->doesntExpectOutputToContain('Bootstrap cache matches environment (doctor)')
        ->assertExitCode(0);
});

it('renders diagnostic links in cli output', function (): void {
    Doctor::diagnostic(LinkedDiagnostic::class);

    $this->artisan('doctor --only=LinkedDiagnostic')
        ->expectsOutputToContain('[warn] Testing diagnostic has links (application): The linked diagnostic warned.')
        ->expectsOutputToContain('    Laravel Docs: https://laravel.com/docs')
        ->assertExitCode(0);
});

it('renders diagnostic links in json output', function (): void {
    Doctor::diagnostic(LinkedDiagnostic::class);

    $this->artisan('doctor --only=LinkedDiagnostic --format=json')
        ->expectsOutputToContain('"links":{"Laravel Docs":"https:\/\/laravel.com\/docs"}')
        ->assertExitCode(0);
});

it('renders diagnostic links in github output', function (): void {
    Doctor::diagnostic(LinkedDiagnostic::class);

    $this->artisan('doctor --only=LinkedDiagnostic --format=github')
        ->expectsOutputToContain('Laravel Docs%3A https%3A//laravel.com/docs')
        ->assertExitCode(0);
});

it('rejects fixes with json output', function (): void {
    $this->artisan('doctor --only=ApplicationKeyIsSet --fix --format=json')
        ->expectsOutputToContain('The --fix option may only be used with --format=cli.')
        ->doesntExpectOutputToContain('"diagnostics"')
        ->assertExitCode(1);
});

it('rejects fixes with github output', function (): void {
    $this->artisan('doctor --only=ApplicationKeyIsSet --fix --format=github')
        ->expectsOutputToContain('The --fix option may only be used with --format=cli.')
        ->doesntExpectOutputToContain('::')
        ->assertExitCode(1);
});

it('validates fail-on before running diagnostics', function (): void {
    Doctor::diagnostic(LinkedDiagnostic::class);

    $this->artisan('doctor --only=LinkedDiagnostic --fail-on=broken')
        ->expectsOutputToContain('The --fail-on option must be one of: fail, warn, never.')
        ->doesntExpectOutputToContain('The linked diagnostic warned.')
        ->assertExitCode(1);
});

it('binds the doctor service and facade', function (): void {
    Doctor::diagnostic(PassingDiagnostic::class);

    expect($this->app->make(Laravel\Doctor\Doctor::class)->registered())
        ->toBe([
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
            PassingDiagnostic::class,
        ]);
});

it('registers the default diagnostics', function (): void {
    expect($this->app->make(Laravel\Doctor\Doctor::class)->registered())
        ->toBe([
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
