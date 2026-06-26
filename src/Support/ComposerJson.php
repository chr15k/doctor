<?php

namespace Laravel\Doctor\Support;

use Illuminate\Support\Arr;

class ComposerJson
{
    /**
     * The decoded composer.json payload.
     *
     * @var array<string, mixed>|null
     */
    private ?array $contents = null;

    /**
     * Determine whether the application has a composer.json file.
     */
    public function exists(): bool
    {
        return is_file($this->path());
    }

    /**
     * Get the application's PHP version constraint.
     */
    public function phpConstraint(): ?string
    {
        $constraint = Arr::get($this->contents() ?? [], 'require.php');

        return is_string($constraint) && $constraint !== '' ? $constraint : null;
    }

    /**
     * Get the Composer-required PHP extensions.
     *
     * @return list<string>
     */
    public function requiredExtensions(): array
    {
        return $this->extensionsFrom([
            ...$this->packageNames(Arr::get($this->contents() ?? [], 'require', [])),
            ...$this->packageNames(Arr::get($this->contents() ?? [], 'require-dev', [])),
        ]);
    }

    /**
     * Get the Composer-suggested PHP extensions.
     *
     * @return list<string>
     */
    public function suggestedExtensions(): array
    {
        return $this->extensionsFrom(
            $this->packageNames(Arr::get($this->contents() ?? [], 'suggest', [])),
        );
    }

    /**
     * Get the decoded composer.json contents.
     *
     * @return array<string, mixed>|null
     */
    public function contents(): ?array
    {
        if ($this->contents !== null) {
            return $this->contents;
        }

        $contents = @file_get_contents($this->path());

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return null;
        }

        $composer = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $composer[$key] = $value;
            }
        }

        return $this->contents = $composer;
    }

    /**
     * Get package names from a Composer object.
     *
     * @return list<string>
     */
    private function packageNames(mixed $packages): array
    {
        if (! is_array($packages)) {
            return [];
        }

        return array_values(array_filter(array_keys($packages), is_string(...)));
    }

    /**
     * Get extension names from Composer package names.
     *
     * @param  list<string>  $packages
     * @return list<string>
     */
    private function extensionsFrom(array $packages): array
    {
        return array_values(array_unique(array_map(
            static fn (string $package): string => substr($package, 4),
            array_filter(
                $packages,
                static fn (string $package): bool => str_starts_with($package, 'ext-'),
            ),
        )));
    }

    /**
     * Get the path to composer.json.
     */
    private function path(): string
    {
        return base_path('composer.json');
    }
}
