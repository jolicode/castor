<?php

namespace Castor;

use Castor\Console\Output\VerbosityLevel;
use Castor\Helper\PathHelper;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class Context implements \ArrayAccess
{
    public readonly string $workingDirectory;

    public readonly bool $supportsInteraction;

    /**
     * @param array<string, string|\Stringable|int>              $environment         A list of environment variables to add to the task
     * @param string[]                                           $verboseArguments    A list of arguments to pass to the command to enable verbose output
     * @param string|\Stringable|resource|\Iterator<string>|null $input               The input to send to the process stdin
     * @param ?bool                                              $supportsInteraction Whether the surrounding environment supports interactive
     *                                                                                commands. When null (default), it is auto-detected from
     *                                                                                well-known signals (CI env var, STDIN being a TTY).
     *
     * @phpstan-param ContextData $data The input parameter accepts an array or an Object
     */
    public function __construct(
        public readonly array $data = [],
        public readonly array $environment = [],
        ?string $workingDirectory = null,
        public readonly bool $tty = false,
        public readonly bool $pty = true,
        public readonly ?float $timeout = null,
        public readonly bool $quiet = false,
        public readonly bool $allowFailure = false,
        public readonly ?bool $notify = null,
        public readonly VerbosityLevel $verbosityLevel = VerbosityLevel::NOT_CONFIGURED,
        /**
         * @internal
         * Do not use this argument, it is only used internally by the application
         */
        public readonly string $name = '',
        public readonly string $notificationTitle = '',
        public readonly array $verboseArguments = [],
        public readonly mixed $input = null,
        ?bool $supportsInteraction = null,
    ) {
        $this->workingDirectory = $workingDirectory ?? PathHelper::getRoot(false);
        $this->supportsInteraction = $supportsInteraction ?? self::detectSupportsInteraction();
    }

    // @phpstan-ignore missingType.iterableValue
    public function getDebugInfo(): array
    {
        return [
            'name' => $this->name,
            'data' => $this->data,
            'environment' => $this->environment,
            'workingDirectory' => $this->workingDirectory,
            'tty' => $this->tty,
            'pty' => $this->pty,
            'timeout' => $this->timeout,
            'quiet' => $this->quiet,
            'allowFailure' => $this->allowFailure,
            'notify' => $this->notify,
            'verbosityLevel' => $this->verbosityLevel,
            'notificationTitle' => $this->notificationTitle,
            'supportsInteraction' => $this->supportsInteraction,
        ];
    }

    /**
     * @param array<(int|string), mixed> $data
     *
     * @throws \Exception
     */
    public function withData(array $data, bool $keepExisting = true, bool $recursive = true): self
    {
        if (false === $keepExisting && true === $recursive) {
            throw new \Exception('You cannot use the recursive option without keeping the existing data');
        }

        if ($keepExisting) {
            if ($recursive) {
                $data = $this->arrayMergeRecursiveDistinct($this->data, $data);
            } else {
                $data = array_merge($this->data, $data);
            }
        }

        return $this->clone([
            'data' => $data,
        ]);
    }

    /** @param array<string, string|\Stringable|int> $environment */
    public function withEnvironment(array $environment, bool $keepExisting = true): self
    {
        return $this->clone([
            'environment' => $keepExisting ? [...$this->environment, ...$environment] : $environment,
        ]);
    }

    public function withWorkingDirectory(string $workingDirectory): self
    {
        return $this->clone([
            'workingDirectory' => str_starts_with($workingDirectory, '/') ? $workingDirectory : PathHelper::realpath($this->workingDirectory . '/' . $workingDirectory),
        ]);
    }

    public function withTty(bool $tty = true): self
    {
        return $this->clone([
            'tty' => $tty,
        ]);
    }

    public function withPty(bool $pty = true): self
    {
        return $this->clone([
            'pty' => $pty,
        ]);
    }

    public function withTimeout(?float $timeout): self
    {
        return $this->clone([
            'timeout' => $timeout,
        ]);
    }

    public function withQuiet(bool $quiet = true): self
    {
        return $this->clone([
            'quiet' => $quiet,
        ]);
    }

    public function withAllowFailure(bool $allowFailure = true): self
    {
        return $this->clone([
            'allowFailure' => $allowFailure,
        ]);
    }

    public function withNotify(?bool $notify = true): self
    {
        return $this->clone([
            'notify' => $notify,
        ]);
    }

    public function withVerbosityLevel(VerbosityLevel $verbosityLevel): self
    {
        return $this->clone([
            'verbosityLevel' => $verbosityLevel,
        ]);
    }

    /**
     * @internal
     * Do not use this method, it is only used internally by the application
     */
    public function withName(string $name): self
    {
        return $this->clone([
            'name' => $name,
        ]);
    }

    public function withNotificationTitle(string $notificationTitle): self
    {
        return $this->clone([
            'notificationTitle' => $notificationTitle,
        ]);
    }

    /** @param string[] $arguments */
    public function withVerboseArguments(array $arguments = []): self
    {
        return $this->clone([
            'verboseArguments' => $arguments,
        ]);
    }

    /** @param string|\Stringable|resource|\Iterator<string>|null $input */
    public function withInput(mixed $input): self
    {
        return $this->clone([
            'input' => $input,
        ]);
    }

    public function withSupportsInteraction(bool $supportsInteraction = true): self
    {
        return $this->clone([
            'supportsInteraction' => $supportsInteraction,
        ]);
    }

    public function supportsInteraction(): bool
    {
        return $this->supportsInteraction;
    }

    /**
     * Switches the context to interactive mode (TTY enabled, no timeout, allow
     * failure) so that commands like a shell can be run.
     *
     * When the surrounding environment is not interactive (CI, AI agent, piped
     * stdin, ...) this throws a {@see \LogicException} to prevent silent hangs.
     * Pass {@code $throwOnNonInteractiveEnv: false} to bypass this check and
     * force interactive flags anyway.
     */
    public function toInteractive(bool $throwOnNonInteractiveEnv = true): self
    {
        if ($throwOnNonInteractiveEnv && !$this->supportsInteraction) {
            throw new \LogicException('Cannot switch context to interactive mode: the surrounding environment is not interactive (CI, agent, or non-TTY stdin). Call toInteractive(throwOnNonInteractiveEnv: false) to force it, or guard the call with Context::supportsInteraction().');
        }

        return $this
            ->withTimeout(null)
            ->withTty()
            ->withAllowFailure()
        ;
    }

    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!\array_key_exists($offset, $this->data)) {
            throw new \OutOfBoundsException(\sprintf('The property "%s" does not exist in the current context.', $offset));
        }

        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Context is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Context is immutable.');
    }

    /**
     * @param array<(int|string), mixed> $array1
     * @param array<(int|string), mixed> $array2
     *
     * @return array<(int|string), mixed>
     */
    private function arrayMergeRecursiveDistinct(array $array1, array $array2): array
    {
        /** @var array<(int|string), mixed> $merged */
        $merged = $array1;
        foreach ($array2 as $key => $value) {
            if (\is_array($value) && isset($merged[$key]) && \is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function clone($args): self
    {
        if (\PHP_VERSION_ID >= 80500) {
            // Leading \ is really important here, to make it work on older PHP versions
            /* @phpstan-ignore-next-line */
            return \clone($this, $args);
        }

        $vars = array_merge(get_object_vars($this), $args);

        return new self(...$vars);
    }

    private static function detectSupportsInteraction(): bool
    {
        if (false !== getenv('CI')) {
            return false;
        }

        if (\defined('STDIN') && \function_exists('stream_isatty') && !@stream_isatty(\STDIN)) {
            return false;
        }

        return true;
    }
}
