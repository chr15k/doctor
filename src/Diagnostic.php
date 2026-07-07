<?php

namespace Laravel\Doctor;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\FixResult;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Results\Status;
use Stringable;

abstract class Diagnostic
{
    public string $name;

    public string $group;

    /**
     * Create a new diagnostic instance.
     */
    public function __construct()
    {
        $this->name ??= Str::of(class_basename(static::class))->snake(' ')->ucfirst()->toString();
        $this->group ??= Str::kebab(class_basename(static::class));
    }

    /**
     * Run the diagnostic.
     */
    abstract public function check(): DiagnosticResult;

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Create a passing diagnostic result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function pass(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Pass, $message, $replace);
    }

    /**
     * Create a failing diagnostic result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function fail(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Fail, $message, $replace);
    }

    /**
     * Create a warning diagnostic result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function warn(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Warn, $message, $replace);
    }

    /**
     * Create a notice diagnostic result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function notice(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Notice, $message, $replace);
    }

    /**
     * Create a skipped diagnostic result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function skip(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Skip, $message, $replace);
    }

    /**
     * Create an error diagnostic result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function error(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Error, $message, $replace);
    }

    /**
     * Create a passing fix result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function fixed(string $message, array $replace = []): FixResult
    {
        return $this->diagnosticFixResult(Status::Pass, $message, $replace);
    }

    /**
     * Create a failing fix result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    protected function fixFailed(string $message, array $replace = []): FixResult
    {
        return $this->diagnosticFixResult(Status::Fail, $message, $replace);
    }

    /**
     * Create a diagnostic result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    private function diagnosticResult(Status $status, string $message, array $replace = []): DiagnosticResult
    {
        $definition = $this->message($message);
        $tokens = $this->tokens($replace);

        $result = new DiagnosticResult(
            $status,
            Str::swap($tokens, $definition->summary),
            $this->code($message),
        );

        if ($definition->remediation !== null) {
            $result->suggest(Str::swap($tokens, $definition->remediation));
        }

        if ($definition->confirmation !== null) {
            $result->confirmUsing(Str::swap($tokens, $definition->confirmation));
        }

        foreach ($definition->links as $label => $url) {
            $result->link(Str::swap($tokens, $label), Str::swap($tokens, $url));
        }

        return $result;
    }

    /**
     * Create a fix result from a named message.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     */
    private function diagnosticFixResult(Status $status, string $message, array $replace = []): FixResult
    {
        $definition = $this->message($message);

        return new FixResult(
            $status,
            Str::swap($this->tokens($replace), $definition->summary),
            $this->fixCode($message),
        );
    }

    /**
     * Get a named message definition.
     */
    protected function message(string $message): Message
    {
        $definition = $this->messages()[$message] ?? null;

        if (is_string($definition)) {
            return Message::make($definition);
        }

        if (! $definition instanceof Message) {
            throw new InvalidArgumentException(sprintf(
                'Message [%s] is not defined for diagnostic [%s].',
                $message,
                static::class,
            ));
        }

        return $definition;
    }

    /**
     * Get a machine-readable result code for the message.
     */
    protected function code(string $message): string
    {
        return Str::of(class_basename(static::class))
            ->kebab()
            ->append('.'.$message)
            ->toString();
    }

    /**
     * Get a machine-readable fix result code for the message.
     */
    protected function fixCode(string $message): string
    {
        return Str::of(class_basename(static::class))
            ->kebab()
            ->append('.fix.'.$message)
            ->toString();
    }

    /**
     * Normalize replacement values into Str::swap tokens.
     *
     * @param  array<string, bool|float|int|string|Stringable|null>  $replace
     * @return array<string, string>
     */
    protected function tokens(array $replace): array
    {
        $tokens = [];

        foreach ($replace as $key => $value) {
            $tokens['{'.$key.'}'] = match (true) {
                $value === null => '',
                is_bool($value) => $value ? 'true' : 'false',
                default => (string) $value,
            };
        }

        return $tokens;
    }

    /**
     * Get the Composer package that provides the diagnostic.
     */
    public function package(): ?string
    {
        return $this->diagnosticSource()->package;
    }

    /**
     * Get the user-facing diagnostic source label.
     */
    public function source(): string
    {
        return $this->package() ?? 'application';
    }

    /**
     * Determine whether the diagnostic belongs to the application source.
     */
    public function application(): bool
    {
        $source = $this->diagnosticSource();

        // A diagnostic may override package() to claim a different source...
        if ($this->package() !== $source->package) {
            return $this->package() === null;
        }

        return $source->application;
    }

    /**
     * Get the resolved diagnostic source metadata.
     */
    public function diagnosticSource(): DiagnosticSource
    {
        return DiagnosticSource::resolve(static::class);
    }
}
