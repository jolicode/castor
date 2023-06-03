<?php

namespace Castor;

class Context implements \ArrayAccess
{
    public readonly string $currentDirectory;

    /**
     * @param array<(int|string), mixed> $data        The input parameter accepts an array or an Object
     * @param array<string, string>      $environment A list of environment variables to add to the command
     * @param array{
     *     'port'?: int,
     *     'path_private_key'?: string,
     *     'jump_host'?: string,
     *     'multiplexing_control_path'?: string,
     *     'multiplexing_control_persist'?: string,
     *     'enable_strict_check'?: bool,
     *     'password_authentication'?: bool,
     * }                                 $sshOptions  Options for SSH connection
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
        public readonly string $host = 'localhost',
        public readonly ?string $user = null,
        public readonly array $sshOptions = [],
    ) {
        $this->currentDirectory = $currentDirectory ?? PathHelper::getRoot();
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
            $this->host,
            $this->user,
            $this->sshOptions,
        );
    }

    /** @param array<string, string> $environment */
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
            $this->host,
            $this->user,
            $this->sshOptions,
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
            $this->host,
            $this->user,
            $this->sshOptions,
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
            $this->host,
            $this->user,
            $this->sshOptions,
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
            $this->host,
            $this->user,
            $this->sshOptions,
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
            $this->host,
            $this->user,
            $this->sshOptions,
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
            $this->host,
            $this->user,
            $this->sshOptions,
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
            $this->host,
            $this->user,
            $this->sshOptions,
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
            $this->host,
            $this->user,
            $this->sshOptions,
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
            $this->host,
            $this->user,
            $this->sshOptions,
        );
    }

    public function withoutSsh(): self
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
            $this->verbosityLevel,
            'localhost',
            null,
            [],
        );
    }

    /**
     * @param array{
     *     'port'?: int,
     *     'path_private_key'?: string,
     *     'jump_host'?: string,
     *     'multiplexing_control_path'?: string,
     *     'multiplexing_control_persist'?: string,
     *     'enable_strict_check'?: bool,
     *     'password_authentication'?: bool,
     * } $sshOptions Options for SSH connection
     */
    public function withSsh(string $host, string $user, array $sshOptions = []): self
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
            $this->verbosityLevel,
            $host,
            $user,
            $sshOptions,
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
