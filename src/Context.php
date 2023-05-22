<?php

namespace Castor;

use ArrayObject;

/**
 * @template TValue
 *
 * @template-extends ArrayObject<(int|string), TValue>
 */
class Context extends \ArrayObject
{
    public string $currentDirectory;

    /**
     * @param array<(int|string), TValue> $data The input parameter accepts an array or an Object
     * @param array<string, string> $environment a list of environment variables to add to the command
     */
    public function __construct(
        array $data = [],
        public array $environment = [],
        string $currentDirectory = null,
        public bool $tty = false,
        public bool $pty = true,
        public float|null $timeout = 60,
    ) {
        parent::__construct($data, \ArrayObject::ARRAY_AS_PROPS);

        $this->currentDirectory = $currentDirectory ?? PathHelper::getRoot();
    }

    public function cd(string $path): void
    {
        // if path is absolute
        if (str_starts_with($path, '/')) {
            $this->currentDirectory = $path;
        } else {
            $this->currentDirectory = PathHelper::realpath($this->currentDirectory . '/' . $path);
        }
    }

    /** @param array<(int|string), TValue> $data */
    public function with(array $data, bool $keepExisting = true): self
    {
        return new self(
            $keepExisting ? array_merge($this->getArrayCopy(), $data) : $data,
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
        );
    }

    /** @param array<string, string> $environment */
    public function withEnvironment(array $environment, bool $keepExisting = true): self
    {
        return new self(
            $this->getArrayCopy(),
            $keepExisting ? array_merge($this->environment, $environment) : $environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
        );
    }

    public function withCd(string $path): self
    {
        $context = clone $this;
        $context->cd($path);

        return $context;
    }

    public function withDirectory(string $directory): self
    {
        return new self(
            $this->getArrayCopy(),
            $this->environment,
            $directory,
            $this->tty,
            $this->pty,
            $this->timeout,
        );
    }

    public function withTty(bool $tty = true): self
    {
        return new self(
            $this->getArrayCopy(),
            $this->environment,
            $this->currentDirectory,
            $tty,
            $this->pty,
            $this->timeout,
        );
    }

    public function withPty(bool $pty = true): self
    {
        return new self(
            $this->getArrayCopy(),
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $pty,
            $this->timeout,
        );
    }

    public function withTimeout(float|null $timeout): self
    {
        return new self(
            $this->getArrayCopy(),
            $this->environment,
            $this->currentDirectory,
            $this->tty,
            $this->pty,
            $timeout,
        );
    }
}
