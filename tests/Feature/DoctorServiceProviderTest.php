<?php

use Illuminate\Contracts\Console\Kernel;
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
use Laravel\Doctor\DoctorServiceProvider;
use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\LinkedDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PackagedNoticeDiagnostic;
use Laravel\Doctor\Tests\Fixtures\Diagnostics\PassingDiagnostic;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

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

    $environmentPath = sys_get_temp_dir().'/laravel-doctor-key-'.str_replace('.', '', uniqid('', true));

    mkdir($environmentPath, 0775, true);
    file_put_contents($environmentPath.'/.env', "APP_KEY=\n");

    $this->app->useEnvironmentPath($environmentPath);

    $this->artisan('doctor --only=ApplicationKeyIsSet --fix')
        ->expectsOutputToContain('[pass] fix Application key is set (laravel/doctor): The application key was generated.')
        ->doesntExpectOutputToContain('[fail] Application key is set (laravel/doctor): Laravel does not have an application key.')
        ->assertExitCode(0);
});

it('renders issue callout sources with package footer', function (): void {
    config(['app.key' => '']);

    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

    $this->app->make(Kernel::class)->call('doctor', [
        '--only' => 'ApplicationKeyIsSet',
        '--fail-on' => 'fail',
        '--no-interaction' => true,
    ], $output);

    expect($output->fetch())
        ->toContain('Laravel does not have an application key.')
        ->toContain('laravel/doctor')
        ->not->toContain('File:')
        ->not->toContain('ApplicationKeyIsSet.php')
        ->not->toContain('laravel/doctor ApplicationKeyIsSet.php');
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

    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

    $exitCode = $this->app->make(Kernel::class)->call('doctor', ['--only' => 'BootstrapCacheMatchesEnvironment'], $output);

    expect($output->fetch())
        ->toContain('Notice')
        ->toContain('Cached bootstrap files detected: events and views.')
        ->toContain('optimize:clear')
        ->not->toContain('Suggested fix')
        ->not->toContain('Notes:')
        ->not->toContain('[notice]')
        ->not->toContain('Bootstrap cache matches environment (laravel/doctor)')
        ->and($exitCode)->toBe(0);
});

it('renders multiple notice diagnostics in a single callout', function (): void {
    $basePath = sys_get_temp_dir().'/laravel-doctor-notices-output-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath.'/bootstrap/cache', 0775, true);
    mkdir($basePath.'/storage/framework/views', 0775, true);

    $this->app->setBasePath($basePath);
    $this->app->useStoragePath($basePath.'/storage');
    config([
        'app.env' => 'local',
        'queue.default' => 'database',
        'view.compiled' => $basePath.'/storage/framework/views',
    ]);

    file_put_contents($this->app->getCachedEventsPath(), '<?php return [];');
    file_put_contents($basePath.'/storage/framework/views/example.php', '<?php echo "cached";');

    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

    $exitCode = $this->app->make(Kernel::class)->call('doctor', [
        '--only' => [
            'BootstrapCacheMatchesEnvironment',
            'AsynchronousQueueIsConfigured',
        ],
    ], $output);

    $contents = $output->fetch();

    expect($contents)
        ->toContain('Notices')
        ->toContain('Cached bootstrap files detected: events and views.')
        ->toContain('Queued jobs are processed asynchronously.')
        ->toContain('laravel/doctor')
        ->toContain('optimize:clear')
        ->toContain('queue:work')
        ->not->toContain('Suggested fix')
        ->and(substr_count($contents, 'Notice'))->toBe(1)
        ->and($exitCode)->toBe(0);
});

it('groups notice diagnostics by package source', function (): void {
    Doctor::diagnostic(PackagedNoticeDiagnostic::class);

    $basePath = sys_get_temp_dir().'/laravel-doctor-notice-packages-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath.'/bootstrap/cache', 0775, true);
    mkdir($basePath.'/storage/framework/views', 0775, true);

    $this->app->setBasePath($basePath);
    $this->app->useStoragePath($basePath.'/storage');
    config([
        'app.env' => 'local',
        'queue.default' => 'database',
        'view.compiled' => $basePath.'/storage/framework/views',
    ]);

    file_put_contents($this->app->getCachedEventsPath(), '<?php return [];');
    file_put_contents($basePath.'/storage/framework/views/example.php', '<?php echo "cached";');

    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

    $exitCode = $this->app->make(Kernel::class)->call('doctor', [
        '--only' => [
            'BootstrapCacheMatchesEnvironment',
            'AsynchronousQueueIsConfigured',
            'PackagedNoticeDiagnostic',
        ],
    ], $output);

    $contents = $output->fetch();

    expect($contents)
        ->toContain('Cached bootstrap files detected: events and views.')
        ->toContain('Queued jobs are processed asynchronously.')
        ->toContain('The packaged diagnostic noticed.')
        ->toContain('laravel/doctor')
        ->toContain('vendor/package')
        ->and(substr_count($contents, 'Notice'))->toBe(2)
        ->and($exitCode)->toBe(0);
});

it('renders diagnostic links in cli output', function (): void {
    Doctor::diagnostic(LinkedDiagnostic::class);

    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

    $exitCode = $this->app->make(Kernel::class)->call('doctor', ['--only' => 'LinkedDiagnostic'], $output);

    expect($output->fetch())
        ->toContain('Testing diagnostic has links')
        ->toContain('The linked diagnostic warned.')
        ->toContain('Detailed link context.')
        ->toContain('Follow the linked documentation.')
        ->not->toContain('[warn]')
        ->toContain('Laravel Docs')
        ->toContain('https://laravel.com/docs')
        ->and($exitCode)->toBe(0);
});

it('renders diagnostic links in json output', function (): void {
    Doctor::diagnostic(LinkedDiagnostic::class);

    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

    $exitCode = $this->app->make(Kernel::class)->call('doctor', [
        '--only' => 'LinkedDiagnostic',
        '--format' => 'json',
    ], $output);

    $payload = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['diagnostics'][0]['source'])->toBe([
        'label' => 'laravel/doctor',
        'package' => 'laravel/doctor',
        'file' => 'tests/Fixtures/Diagnostics/LinkedDiagnostic.php',
        'application' => true,
    ])
        ->and($payload['diagnostics'][0]['links'])->toBe(['Laravel Docs' => 'https://laravel.com/docs'])
        ->and($exitCode)->toBe(0);
});

it('renders diagnostic links in github output', function (): void {
    Doctor::diagnostic(LinkedDiagnostic::class);

    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

    $exitCode = $this->app->make(Kernel::class)->call('doctor', [
        '--only' => 'LinkedDiagnostic',
        '--format' => 'github',
    ], $output);

    expect($output->fetch())
        ->toContain('title=Testing diagnostic has links (laravel/doctor)')
        ->toContain('Laravel Docs%3A https%3A//laravel.com/docs')
        ->and($exitCode)->toBe(0);
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
            PassingDiagnostic::class,
        ]);
});

it('registers the default diagnostics', function (): void {
    expect($this->app->make(Laravel\Doctor\Doctor::class)->registered())
        ->toBe([
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
