<p align="center">
<a href="https://github.com/laravel/doctor/actions"><img src="https://github.com/laravel/doctor/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/doctor"><img src="https://img.shields.io/packagist/dt/laravel/doctor" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/doctor"><img src="https://img.shields.io/packagist/v/laravel/doctor" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/doctor"><img src="https://img.shields.io/packagist/l/laravel/doctor" alt="License"></a>
</p>

## Introduction

Laravel Doctor diagnoses common configuration, environment, and infrastructure problems in your application and reports them with actionable remediation.

Each diagnostic is a single check. It inspects one thing, such as whether Laravel can write to its storage directories, and reports a pass, warning, or failure. A diagnostic may also offer a fix when the repair is safe and deterministic. Issues that can't be repaired safely, such as a failed asset build, are reported with remediation steps for you to follow instead.

## Installation

Install Laravel Doctor using Composer:

```bash
composer require laravel/doctor --dev
```

Once installed, the package registers the `doctor` Artisan command:

```bash
php artisan doctor
```

## Running Doctor

Run every default diagnostic with:

```bash
php artisan doctor
```

When a failing diagnostic can be fixed, Doctor shows where it came from and prompts before applying the fix:

```text
Fix available for Writable storage directories (internal): Laravel cannot write to every required storage directory.
```

To run available fixes without prompting, pass the `--fix` option:

```bash
php artisan doctor --fix
```

## Selecting Diagnostics

Diagnostics may be selected by class name, group, or package. The built-in writable storage diagnostic belongs to the `storage` group and uses the `WritableStorage` class name:

```bash
php artisan doctor --only=storage

php artisan doctor --only=WritableStorage

php artisan doctor --only=vendor/package
```

You may also choose diagnostic groups interactively:

```bash
php artisan doctor --interactive
```

## Default Diagnostics

Doctor ships with a growing suite of diagnostics that cover the most common ways a Laravel application is misconfigured. They span the following areas:

- **Environment** — `.env` or `.env.*` presence, `APP_KEY`, and PHP extensions required by `composer.json`.
- **Composer** — `vendor/autoload.php` exists, Composer can dump optimized autoload files, and `composer.lock` is present and fresh.
- **Configuration** — configuration files can be loaded without exceptions.
- **Database** — the default connection is reachable, and pending migrations are reported.
- **Cache, queue & session** — drivers are reachable, and `sync` or `array` drivers are flagged in production.
- **Storage** — required directories are writable and the `storage:link` symlink exists when expected.
- **Security** — `APP_DEBUG` is off in production, `.env` is ignored by Git, and `composer audit` reports no vulnerable packages.
- **Deployment** — caches are warm, the autoloader is optimized, and opcache is enabled in production.

The remaining diagnostics are tracked in [DIAGNOSTICS.md](DIAGNOSTICS.md) as the suite grows. Each entry is a single check with one optional fix.

## Creating Diagnostics

A diagnostic is a single class that, like an Artisan command, declares its metadata as properties. Extend `Laravel\Doctor\Diagnostics\Diagnostic` and implement a `check` method that returns a `DiagnosticResult`.

To offer a fix, implement the `Laravel\Doctor\Contracts\Fixable` contract. Doctor only attempts a fix on diagnostics that implement it, so most diagnostics need nothing more than a `check` method.

The following diagnostic checks whether the configured SQLite database exists. Because creating the file is safe, it implements `Fixable`:

```php
<?php

namespace App\Doctor\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Contracts\ProcessRunner;
use Laravel\Doctor\Diagnostics\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;

class SqliteDatabaseExists extends Diagnostic implements Fixable
{
    public string $name = 'SQLite database exists';

    public string $group = 'database';

    public ?string $fixPrompt = 'Would you like Doctor to create the SQLite database file?';

    public function __construct(private ProcessRunner $process)
    {
        //
    }

    public function check(): DiagnosticResult
    {
        if (config('database.default') !== 'sqlite') {
            return DiagnosticResult::skip('The default database connection is not SQLite.');
        }

        $database = config('database.connections.sqlite.database');

        if (is_string($database) && is_file($database)) {
            return DiagnosticResult::pass('The SQLite database file exists.');
        }

        return DiagnosticResult::fail('The SQLite database file does not exist.')
            ->command('touch '.$database, 'Create the missing SQLite database file.');
    }

    public function fix(DiagnosticResult $result): FixResult
    {
        $database = config('database.connections.sqlite.database');

        $process = $this->process->run(['touch', $database], base_path());

        if (! $process->successful()) {
            return FixResult::fail('The SQLite database file could not be created.')
                ->withDetails($process->errorOutput);
        }

        return FixResult::pass('The SQLite database file was created.');
    }
}
```

The `check` method detects the problem; the `fix` method receives the failing result so it can act on what the check found. Implement `Fixable` only when the repair is predictable and safe. Otherwise, return remediation text from your check and let the developer resolve it.

## Registering Diagnostics

Applications may register diagnostics through the Doctor facade, typically from a service provider:

```php
use App\Doctor\Diagnostics\SqliteDatabaseExists;
use Laravel\Doctor\Facades\Doctor;

public function boot(): void
{
    Doctor::diagnostic(SqliteDatabaseExists::class);
}
```

Packages use the same registration API from their service providers:

```php
use Laravel\Doctor\Facades\Doctor;
use Vendor\Package\Diagnostics\SqliteDatabaseExists;

public function boot(): void
{
    Doctor::diagnostic(SqliteDatabaseExists::class);
}
```

Doctor determines where a diagnostic came from by inspecting the diagnostic class file and matching it to Composer's installed package paths. Application diagnostics and Doctor's own diagnostics are shown as `internal`; diagnostics shipped by other Composer packages are shown as `package [vendor/package]`.

## Output Formats

Doctor renders readable CLI output by default. JSON and GitHub Actions annotations are also available:

```bash
php artisan doctor --format=json

php artisan doctor --format=github
```

The command exits with a failing status when a diagnostic fails. Use `--fail-on=warn` to also fail on warnings, or `--fail-on=never` when Doctor should only report issues.

## Contributing

Thank you for considering contributing to Laravel Doctor! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/laravel/doctor/security/policy) on how to report security vulnerabilities.

## License

Laravel Doctor is open-sourced software licensed under the [MIT license](LICENSE.md).
