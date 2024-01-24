<?php

namespace Castor;

class Context implements \ArrayAccess
{
    public readonly string $currentDirectory;

    /**
     * @phpstan-param ContextData $data The input parameter accepts an array or an Object
     *
     * @param array<string, string|\Stringable|int> $environment A list of environment variables to add to the task
     */
    public function __construct(
        public readonly array $data = [],
        public readonly array $environment = [],
        string $currentDirectory = null,
        public readonly bool $tty = false,
        public readonly bool $pty = true,
        public readonly float|null $timeout = 60,
        public readonly bool $quiet = false,
        public readonly bool $allowFailure = false,
        public readonly bool $notify = false,
        public readonly VerbosityLevel $verbosityLevel = VerbosityLevel::NOT_CONFIGURED,
    ) {
        $this->currentDirectory = $currentDirectory ?? PathHelper::getRoot();
    }

    public function __debugInfo()
    {
        return [
            'data' => $this->data,
            'environment' => $this->environment,
            'currentDirectory' => $this->currentDirectory,
            'tty' => $this->tty,
            'pty' => $this->pty,
            'timeout' => $this->timeout,
            'quiet' => $this->quiet,
            'allowFailure' => $this->allowFailure,
            'notify' => $this->notify,
            'verbosityLevel' => $this->verbosityLevel,
        ];
    }

    /** @param array<(int|string), mixed> $data */
    public function withData(array $data, bool $keepExisting = true): self
    {
        return new self(
            $keepExisting ? array_merge($this->data, $data) : $data,
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
        );
    }

    /** @param array<string, string|\Stringable|int> $environment */
    public function withEnvironment(array $environment, bool $keepExisting = true): self
    {
        return new self(
            $this->data,
            $keepExisting ? [...$this->environment, ...$environment] : $environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
        );
    }

    public function withPath(string $path): self
    {
        return new self(
            $this->data,
            $this->environment,
            str_starts_with($path, '/') ? $path : PathHelper::realpath($this->currentDirectory . '/' . $path),
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
        );
    }

    public function withTty(bool $tty = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->currentDirectory,
            $tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
        );
    }

    public function withPty(bool $pty = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
        );
    }

    public function withTimeout(float|null $timeout): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
        );
    }

    public function withQuiet(bool $quiet = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
        );
    }

    public function withAllowFailure(bool $allowFailure = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $allowFailure,
            $this->notify,
            $this->verbosityLevel,
        );
    }

    public function withNotify(bool $notify = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $notify,
            $this->verbosityLevel,
        );
    }

    public function withVerbosityLevel(VerbosityLevel $verbosityLevel): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $verbosityLevel,
        );
    }

    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!\array_key_exists($offset, $this->data)) {
            throw new \OutOfBoundsException(sprintf('The property "%s" does not exist in the current context.', $offset));
        }

        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Context is immutable');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Context is immutable');
    }
}
