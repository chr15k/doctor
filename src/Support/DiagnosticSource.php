<?php

declare(strict_types=1);

namespace Laravel\Doctor\Support;

use Composer\InstalledVersions;
use ReflectionClass;
use Throwable;

class DiagnosticSource
{
    /**
     * @var array<class-string, string|null>
     */
    private static array $packages = [];

    /**
     * @param  class-string  $class
     */
    public static function package(string $class): ?string
    {
        if (! array_key_exists($class, self::$packages)) {
            self::$packages[$class] = self::resolvePackage($class);
        }

        return self::$packages[$class];
    }

    /**
     * @param  class-string  $class
     */
    private static function resolvePackage(string $class): ?string
    {
        $file = self::classFile($class);

        if ($file === null) {
            return null;
        }

        $package = self::installedPackageForFile($file);

        return $package === 'laravel/doctor' ? null : $package;
    }

    /**
     * @param  class-string  $class
     */
    private static function classFile(string $class): ?string
    {
        try {
            $file = (new ReflectionClass($class))->getFileName();
        } catch (Throwable) {
            return null;
        }

        if ($file === false) {
            return null;
        }

        $realPath = realpath($file);

        return $realPath === false ? null : $realPath;
    }

    private static function installedPackageForFile(string $file): ?string
    {
        $match = null;
        $matchLength = -1;

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            try {
                $path = InstalledVersions::getInstallPath($package);
            } catch (Throwable) {
                continue;
            }

            if (! is_string($path)) {
                continue;
            }

            $realPath = realpath($path);

            if ($realPath === false || ! self::contains($realPath, $file)) {
                continue;
            }

            $length = strlen($realPath);

            if ($length > $matchLength) {
                $match = $package;
                $matchLength = $length;
            }
        }

        return $match;
    }

    private static function contains(string $directory, string $file): bool
    {
        return $file === $directory || str_starts_with($file, $directory.DIRECTORY_SEPARATOR);
    }
}
