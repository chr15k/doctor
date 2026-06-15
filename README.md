<p align="center">
<a href="https://github.com/laravel/doctor/actions"><img src="https://github.com/laravel/doctor/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/doctor"><img src="https://img.shields.io/packagist/dt/laravel/doctor" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/doctor"><img src="https://img.shields.io/packagist/v/laravel/doctor" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/doctor"><img src="https://img.shields.io/packagist/l/laravel/doctor" alt="License"></a>
</p>

## Introduction

Laravel Doctor diagnoses common configuration, environment, and infrastructure problems in your application.

Each diagnostic is a single check. It inspects one thing, such as whether Laravel can write to its storage directories, and reports one of several statuses. A diagnostic may also offer a fix when the repair is safe and deterministic. Issues that can't be repaired safely, such as a failed asset build, are reported with remediation steps instead.

## Installation

Install Laravel Doctor using Composer:

```bash
composer require laravel/doctor --dev
```

## Running Doctor

Once installed, the package registers the `doctor` Artisan command:

```bash
php artisan doctor
```

When a failing diagnostic can be fixed, Doctor reports the problem and prompts before applying the fix:

```text
Storage is writable: Laravel cannot write to every required storage directory.

 Would you like Doctor to make Laravel's storage directories writable? (yes/no) [yes]
```

To run available fixes without prompting, pass the `--fix` option:

```bash
php artisan doctor --fix
```

> [!NOTE]
> Fixes are only available with the default CLI output. Doctor rejects `--fix` when `--format=json` or `--format=github` is selected so machine-readable reports never mutate the application.

## Diagnostic Statuses

Every diagnostic returns one of the following statuses. Doctor uses them to render output and to determine the command's exit code:

| Status   | Meaning                                                 | Affects exit code          |
| -------- | ------------------------------------------------------- | -------------------------- |
| `pass`   | The check succeeded and nothing is wrong.               | No                         |
| `notice` | Informational context worth surfacing to the developer. | No                         |
| `warn`   | A potential problem that may not require action.        | Only with `--fail-on=warn` |
| `fail`   | The check found a problem that should be resolved.      | Yes                        |
| `skip`   | The check did not apply to the current environment.     | No                         |
| `error`  | The diagnostic threw an exception while running.        | Yes                        |

## Selecting Diagnostics

Diagnostics may be selected or excluded by class name, group, or package. Multiple values may be passed either by repeating the option or by separating values with commas.

```bash
php artisan doctor --only=storage

php artisan doctor --only=StorageIsWritable

php artisan doctor --only=vendor/package

php artisan doctor --except=storage
```

You may also choose diagnostic groups interactively:

```bash
php artisan doctor --interactive
```

## Default Diagnostics

Doctor ships with a focused suite of diagnostics that cover common configuration, environment, dependency, database, queue, and storage problems. The default suite includes:

- **Environment** — `.env` presence, `APP_KEY`, and PHP extensions required by `composer.json`.
- **Composer** — `vendor/autoload.php` exists, Composer can dump optimized autoload files, and `composer.lock` is present and fresh.
- **Configuration** — configuration files can be loaded without exceptions, and bootstrap cache files are reported when their presence does not match the current environment.
- **Database** — the default connection is reachable, the SQLite database file exists when needed, and pending migrations are reported.
- **Queue** — asynchronous queue connections are noted in local and testing environments so developers know a worker must be running.
- **Storage** — required directories are writable and the `storage:link` symlink exists when expected.

## Creating Diagnostics

A diagnostic is a single class that, like an Artisan command, can declare its definition as properties. Extend `Laravel\Doctor\Diagnostic` and implement a `check` method that returns a `DiagnosticResult`. When `$name` or `$group` are not set, Doctor derives defaults from the class name.

To offer a fix, implement the `Laravel\Doctor\Contracts\Fixable` contract. Doctor only attempts a fix on diagnostics that implement it.

The following diagnostic checks whether the configured SQLite database exists. Because creating the file is safe, it implements `Fixable`:

```php
<?php

namespace App\Doctor\Diagnostics;

use Laravel\Doctor\Contracts\Fixable;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Illuminate\Support\Facades\File;

class SqliteDatabaseExists extends Diagnostic implements Fixable
{
    public string $name = 'SQLite database exists';

    public string $group = 'database';

    public function check(): DiagnosticResult
    {
        if (config('database.default') !== 'sqlite') {
            return DiagnosticResult::skip('The default database connection is not SQLite.');
        }

        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            return DiagnosticResult::skip('The SQLite connection does not use a database file.');
        }

        if (! str_starts_with($database, DIRECTORY_SEPARATOR)) {
            $database = base_path($database);
        }

        if (is_file($database)) {
            return DiagnosticResult::pass('The SQLite database file exists.')
                ->withContext('database', $database);
        }

        return DiagnosticResult::fail('The SQLite database file does not exist.')
            ->withContext('database', $database)
            ->confirmUsing('Would you like Doctor to create the SQLite database file?')
            ->suggest('Create the SQLite database file at the configured path.');
    }

    public function fix(DiagnosticResult $result): FixResult
    {
        $database = $result->context['database'] ?? null;

        if (! is_string($database) || $database === '') {
            return FixResult::fail('The SQLite database file path was not available from the diagnostic result.');
        }

        if (is_file($database)) {
            return FixResult::skip('The SQLite database file already exists.');
        }

        File::ensureDirectoryExists(dirname($database));

        if (File::put($database, '') === false) {
            return FixResult::fail('The SQLite database file could not be created.');
        }

        return FixResult::pass('The SQLite database file was created.');
    }
}
```

The `check` method detects the problem and can attach structured context to the result with `withContext()`. Fixable diagnostics can set the interactive prompt with `confirmUsing()`. Suggestions added with `suggest()` are rendered as remediation text, and links added with `link()` are rendered in CLI, JSON, and GitHub output. The `fix` method receives the failing result and should use that context instead of recomputing the same state. Implement `Fixable` only when the repair is predictable and safe.

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

Doctor determines where a diagnostic came from by inspecting the diagnostic class file and matching it to Composer's installed package paths. Application diagnostics are shown as `application`, Doctor's bundled diagnostics are shown as `doctor`, and diagnostics shipped by other Composer packages are shown as `package [vendor/package]`.

## Output Formats

Doctor renders readable CLI output by default. JSON and GitHub Actions annotations are also available:

```bash
php artisan doctor --format=json

php artisan doctor --format=github
```

The command exits with a failing status when a diagnostic fails. Use `--fail-on=warn` to also fail on warnings, or `--fail-on=never` when Doctor should only report issues. Notices are informational and do not affect the exit code.

## Contributing

Thank you for considering contributing to Laravel Doctor! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/laravel/doctor/security/policy) on how to report security vulnerabilities.

## License

Laravel Doctor is open-sourced software licensed under the [MIT license](LICENSE.md).
