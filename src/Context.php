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
        ?string $currentDirectory = null,
        public readonly bool $tty = false,
        public readonly bool $pty = true,
        public readonly ?float $timeout = null,
        public readonly bool $quiet = false,
        public readonly bool $allowFailure = false,
        public readonly bool $notify = false,
        public readonly VerbosityLevel $verbosityLevel = VerbosityLevel::NOT_CONFIGURED,
        // Do not use this argument, it is only used internally by the application
        public readonly string $name = '',
    ) {
        $this->currentDirectory = $currentDirectory ?? PathHelper::getRoot();
    }

    public function __debugInfo()
    {
        return [
            'name' => $this->name,
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

        return $this->with(data: $data);
    }

    /** @param array<string, string|\Stringable|int> $environment */
    public function withEnvironment(array $environment, bool $keepExisting = true): self
    {
        return $this->with(environment: $keepExisting ? [...$this->environment, ...$environment] : $environment);
    }

    public function withPath(string $path): self
    {
        trigger_deprecation('castor', '0.15', 'The method "%s()" is deprecated, use "%s::withCurrentDirectory()" instead.', __METHOD__, __CLASS__);

        return $this->withCurrentDirectory($path);
    }

    public function withCurrentDirectory(string $currentDirectory): self
    {
        return $this->with(currentDirectory: str_starts_with($currentDirectory, '/') ? $currentDirectory : PathHelper::realpath($this->currentDirectory . '/' . $currentDirectory));
    }

    public function withTty(bool $tty = true): self
    {
        return $this->with(tty: $tty);
    }

    public function withPty(bool $pty = true): self
    {
        return $this->with(pty: $pty);
    }

    public function withTimeout(?float $timeout): self
    {
        return $this->with(timeout: $timeout);
    }

    public function withQuiet(bool $quiet = true): self
    {
        return $this->with(quiet: $quiet);
    }

    public function withAllowFailure(bool $allowFailure = true): self
    {
        return $this->with(allowFailure: $allowFailure);
    }

    public function withNotify(bool $notify = true): self
    {
        return $this->with(notify: $notify);
    }

    public function withVerbosityLevel(VerbosityLevel $verbosityLevel): self
    {
        return $this->with(verbosityLevel: $verbosityLevel);
    }

    public function withName(string $name): self
    {
        return $this->with(name: $name);
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
        throw new \LogicException('Context is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Context is immutable.');
    }

    private function with(mixed ...$args): self
    {
        return new self(
            $args['data'] ?? $this->data,
            $args['environment'] ?? $this->environment,
            $args['currentDirectory'] ?? $this->currentDirectory,
            $args['tty'] ?? $this->tty,
            $args['pty'] ?? $this->pty,
            $args['timeout'] ?? $this->timeout,
            $args['quiet'] ?? $this->quiet,
            $args['allowFailure'] ?? $this->allowFailure,
            $args['notify'] ?? $this->notify,
            $args['verbosityLevel'] ?? $this->verbosityLevel,
            $args['name'] ?? $this->name,
        );
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
}
