<?php

namespace Castor;

use Castor\Console\Output\VerbosityLevel;
use Castor\Helper\PathHelper;
use Castor\VerbosityLevel as LegacyVerbosityLevel;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class Context implements \ArrayAccess
{
    public readonly string $workingDirectory;

    /**
     * @phpstan-param ContextData $data The input parameter accepts an array or an Object
     *
     * @param array<string, string|\Stringable|int> $environment A list of environment variables to add to the task
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
        public readonly VerbosityLevel|LegacyVerbosityLevel $verbosityLevel = VerbosityLevel::NOT_CONFIGURED,
        // Do not use this argument, it is only used internally by the application
        public readonly string $name = '',
        public readonly string $notificationTitle = '',
    ) {
        $this->workingDirectory = $workingDirectory ?? PathHelper::getRoot();
    }

    public function __debugInfo()
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

        return new self(
            $data,
            $this->environment,
            $this->workingDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    /** @param array<string, string|\Stringable|int> $environment */
    public function withEnvironment(array $environment, bool $keepExisting = true): self
    {
        return new self(
            $this->data,
            $keepExisting ? [...$this->environment, ...$environment] : $environment,
            $this->workingDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    public function withPath(string $path): self
    {
        trigger_deprecation('castor', '0.15', 'The method "%s()" is deprecated, use "%s::withWorkingDirectory()" instead.', __METHOD__, __CLASS__);

        return $this->withWorkingDirectory($path);
    }

    public function withWorkingDirectory(string $workingDirectory): self
    {
        return new self(
            $this->data,
            $this->environment,
            str_starts_with($workingDirectory, '/') ? $workingDirectory : PathHelper::realpath($this->workingDirectory . '/' . $workingDirectory),
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    public function withTty(bool $tty = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->workingDirectory,
            $tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    public function withPty(bool $pty = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->workingDirectory,
            $this->tty,
            $pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    public function withTimeout(?float $timeout): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->workingDirectory,
            $this->tty,
            $this->pty,
            $timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    public function withQuiet(bool $quiet = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->workingDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    public function withAllowFailure(bool $allowFailure = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->workingDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    public function withNotify(?bool $notify = true): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->workingDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $notify,
            $this->verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    public function withVerbosityLevel(VerbosityLevel|LegacyVerbosityLevel $verbosityLevel): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->workingDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $verbosityLevel,
            $this->name,
            $this->notificationTitle,
        );
    }

    public function withName(string $name): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->workingDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $name,
            $this->notificationTitle,
        );
    }

    public function withNotificationTitle(string $notificationTitle): self
    {
        return new self(
            $this->data,
            $this->environment,
            $this->workingDirectory,
            $this->tty,
            $this->pty,
            $this->timeout,
            $this->quiet,
            $this->allowFailure,
            $this->notify,
            $this->verbosityLevel,
            $this->name,
            $notificationTitle,
        );
    }

    public function toInteractive(): self
    {
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
}
