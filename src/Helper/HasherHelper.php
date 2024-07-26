<?php

namespace Castor\Helper;

use Castor\Fingerprint\FileHashStrategy;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Finder\Finder;

/** @final */
#[Exclude]
class HasherHelper
{
    private readonly \HashContext $hashContext;

    /**
     * @see https://www.php.net/manual/en/function.hash-algos.php
     */
    public function __construct(
        private readonly Command $command,
        private readonly InputInterface $input,
        private readonly LoggerInterface $logger = new NullLogger(),
        string $algo = 'xxh128',
    ) {
        $this->hashContext = hash_init($algo);
    }

    public function write(string $value): self
    {
        $this->logger->debug('Hashing value "{value}".', ['value' => $value]);

        hash_update($this->hashContext, $value);

        return $this;
    }

    public function writeFile(string $path, FileHashStrategy $strategy = FileHashStrategy::MTimes): self
    {
        if (!str_starts_with($path, '/')) {
            $path = getcwd() . '/' . $path;
        }

        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('The path "%s" is not a file.', $path));
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" is not readable.', $path));
        }

        $this->logger->debug('Hashing file "{path}" with strategy "{strategy}".', [
            'path' => $path,
            'strategy' => $strategy->name,
        ]);

        switch ($strategy) {
            case FileHashStrategy::Content:
                hash_update_file($this->hashContext, $path);

                break;
            case FileHashStrategy::MTimes:
                hash_update($this->hashContext, \sprintf('%s:%s', $path, filemtime($path)));

                break;
        }

        return $this;
    }

    public function writeWithFinder(Finder $finder, FileHashStrategy $strategy = FileHashStrategy::MTimes): self
    {
        $this->logger->debug('Hashing files with Finder with strategy "{strategy}".', [
            'strategy' => $strategy->name,
        ]);

        foreach ($finder as $file) {
            switch ($strategy) {
                case FileHashStrategy::Content:
                    hash_update_file($this->hashContext, $file->getPathname());

                    break;
                case FileHashStrategy::MTimes:
                    hash_update($this->hashContext, "{$file->getPathname()}:{$file->getMTime()}");

                    break;
            }
        }

        return $this;
    }

    public function writeGlob(string $pattern, FileHashStrategy $strategy = FileHashStrategy::MTimes): self
    {
        $this->logger->debug('Hashing files {pattern} with strategy "{strategy}".', [
            'pattern' => $pattern,
            'strategy' => $strategy->name,
        ]);

        $files = glob($pattern);

        if (false === $files) {
            throw new \InvalidArgumentException(\sprintf('The pattern "%s" is invalid.', $pattern));
        }

        foreach ($files as $file) {
            switch ($strategy) {
                case FileHashStrategy::Content:
                    hash_update_file($this->hashContext, $file);

                    break;
                case FileHashStrategy::MTimes:
                    $modifiedTime = filemtime($file);
                    hash_update($this->hashContext, "{$file}:{$modifiedTime}");

                    break;
            }
        }

        return $this;
    }

    public function writeTaskName(): self
    {
        $taskName = $this->command->getName() ?? 'n/a';

        $this->logger->debug('Hashing task name "{name}".', [
            'name' => $taskName,
        ]);

        hash_update($this->hashContext, $taskName);

        return $this;
    }

    public function writeTaskArgs(string ...$args): self
    {
        $this->logger->debug('Hashing task args "{args}".', [
            'args' => implode(', ', $args),
        ]);

        foreach ($args as $arg) {
            if ($this->input->hasArgument($arg)) {
                $this->write($this->input->getArgument($arg));
            }
        }

        return $this;
    }

    public function writeTask(string ...$args): self
    {
        $this->writeTaskName();
        $this->writeTaskArgs(...$args);

        return $this;
    }

    public function finish(): string
    {
        return hash_final($this->hashContext);
    }
}
