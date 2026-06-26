<?php

namespace Laravel\Doctor\Diagnostics;

use Illuminate\Support\Facades\File;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Support\EnvironmentVariables;

class ConfigurationEnvironmentVariablesExist extends Diagnostic
{
    public string $name = 'Configuration environment variables exist';

    public string $group = 'configuration';

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $missing = $this->missingVariables();

        if ($missing === []) {
            return DiagnosticResult::pass('Every required configuration environment variable is defined.');
        }

        return DiagnosticResult::fail('Some configuration environment variables are missing.')
            ->withDetails($this->formatMissingVariables($missing))
            ->suggest('Define the missing variables in the application environment or add safe defaults to the config files.');
    }

    /**
     * Get missing required environment variables referenced from config files.
     *
     * @return array<string, list<string>>
     */
    private function missingVariables(): array
    {
        $missing = [];
        $environment = new EnvironmentVariables;

        foreach ($this->requiredVariables() as $variable => $files) {
            if (! $environment->has($variable)) {
                $missing[$variable] = $files;
            }
        }

        return $missing;
    }

    /**
     * Get required environment variables referenced from config files.
     *
     * @return array<string, list<string>>
     */
    private function requiredVariables(): array
    {
        $variables = [];

        foreach ($this->configurationFiles() as $file) {
            foreach ($this->envCalls($file) as $call) {
                if ($call['hasDefault']) {
                    continue;
                }

                $variables[$call['name']][] = basename($file);
            }
        }

        $normalized = [];

        foreach ($variables as $variable => $files) {
            $normalized[$variable] = $files;
        }

        return $normalized;
    }

    /**
     * Get configuration files.
     *
     * @return list<string>
     */
    private function configurationFiles(): array
    {
        $files = File::glob(base_path('config/*.php')) ?: [];
        $configured = [];

        foreach ($files as $file) {
            if (is_string($file)) {
                $configured[] = $file;
            }
        }

        return $configured;
    }

    /**
     * Get env() calls from a configuration file.
     *
     * @return list<array{name: string, hasDefault: bool}>
     */
    private function envCalls(string $file): array
    {
        $calls = [];
        $tokens = token_get_all(File::get($file));

        foreach ($tokens as $index => $token) {
            if (! $this->isEnvFunctionToken($token)) {
                continue;
            }

            $call = $this->parseEnvCall($tokens, $index);

            if ($call !== null) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * Determine whether a token is the env helper name.
     */
    private function isEnvFunctionToken(mixed $token): bool
    {
        return is_array($token)
            && $token[0] === T_STRING
            && is_string($token[1] ?? null)
            && strtolower($token[1]) === 'env';
    }

    /**
     * Parse an env() call.
     *
     * @param  array<int, mixed>  $tokens
     * @return array{name: string, hasDefault: bool}|null
     */
    private function parseEnvCall(array $tokens, int $index): ?array
    {
        $open = $this->nextNonWhitespaceTokenIndex($tokens, $index + 1);

        if ($open === null || $this->tokenText($tokens[$open]) !== '(') {
            return null;
        }

        $name = $this->firstArgument($tokens, $open);

        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'hasDefault' => $this->hasSecondArgument($tokens, $open),
        ];
    }

    /**
     * Get the first string argument from an env() call.
     *
     * @param  array<int, mixed>  $tokens
     */
    private function firstArgument(array $tokens, int $open): ?string
    {
        $index = $this->nextNonWhitespaceTokenIndex($tokens, $open + 1);

        return $index === null ? null : $this->quotedStringValue($tokens[$index]);
    }

    /**
     * Determine whether an env() call has a second argument.
     *
     * @param  array<int, mixed>  $tokens
     */
    private function hasSecondArgument(array $tokens, int $open): bool
    {
        $depth = 0;

        for ($index = $open; $index < count($tokens); $index++) {
            $text = $this->tokenText($tokens[$index]);

            if ($text === '(') {
                $depth++;
            }

            if ($text === ')') {
                $depth--;

                if ($depth === 0) {
                    return false;
                }
            }

            if ($text === ',' && $depth === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the next non-whitespace token index.
     *
     * @param  array<int, mixed>  $tokens
     */
    private function nextNonWhitespaceTokenIndex(array $tokens, int $start): ?int
    {
        for ($index = $start; $index < count($tokens); $index++) {
            if (! $this->isWhitespaceToken($tokens[$index])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Determine whether the token is whitespace.
     */
    private function isWhitespaceToken(mixed $token): bool
    {
        return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }

    /**
     * Get the value from a quoted string token.
     */
    private function quotedStringValue(mixed $token): ?string
    {
        if (! is_array($token)
            || $token[0] !== T_CONSTANT_ENCAPSED_STRING
            || ! is_string($token[1] ?? null)) {
            return null;
        }

        return stripcslashes(substr($token[1], 1, -1));
    }

    /**
     * Get a token's source text.
     */
    private function tokenText(mixed $token): string
    {
        if (is_string($token)) {
            return $token;
        }

        return is_array($token) && is_string($token[1] ?? null) ? $token[1] : '';
    }

    /**
     * Format missing variables.
     *
     * @param  array<string, list<string>>  $missing
     */
    private function formatMissingVariables(array $missing): string
    {
        return implode(PHP_EOL, array_map(
            static fn (string $variable, array $files): string => sprintf('- %s (%s)', $variable, implode(', ', array_unique($files))),
            array_keys($missing),
            $missing,
        ));
    }
}
