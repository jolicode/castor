<?php

namespace Castor;

use Castor\Fingerprint\FileHashStrategy;
use Symfony\Component\Finder\Finder;

class HasherHelper
{
    private \HashContext $hashContext;

    /**
     * @see https://www.php.net/manual/en/function.hash-algos.php
     */
    public function __construct(string $algo = 'md5')
    {
        $this->hashContext = hash_init($algo);
    }

    public function write(string $value): self
    {
        log('Hashing value "{value}"', 'debug', ['value' => $value]);
        hash_update($this->hashContext, $value);

        return $this;
    }

    public function writeFile(string $path, FileHashStrategy $strategy = FileHashStrategy::MTimes): self
    {
        if (!str_starts_with($path, '/')) {
            $path = getcwd() . '/' . $path;
        }

        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('The path "%s" is not a file', $path));
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" is not readable', $path));
        }

        log('Hashing file "{path}" with strategy "{strategy}"', 'debug', [
            'path' => $path,
            'strategy' => $strategy->name,
        ]);
        switch ($strategy) {
            case FileHashStrategy::Content:
                hash_update_file($this->hashContext, $path);

                break;
            case FileHashStrategy::MTimes:
                hash_update($this->hashContext, sprintf('%s:%s', $path, filemtime($path)));

                break;
        }

        return $this;
    }

    public function writeWithFinder(Finder $finder, FileHashStrategy $strategy = FileHashStrategy::MTimes): self
    {
        log('Hashing files with Finder with strategy "{strategy}"', 'debug', [
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
        log('Hashing files {pattern} with strategy "{strategy}"', 'debug', [
            'pattern' => $pattern,
            'strategy' => $strategy->name,
        ]);
        $files = glob($pattern);

        if (false === $files) {
            throw new \InvalidArgumentException(sprintf('The pattern "%s" is invalid', $pattern));
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
        log('Hashing task name "{name}"', 'debug', [
            'name' => GlobalHelper::getApplication()->getName(),
        ]);
        hash_update($this->hashContext, GlobalHelper::getApplication()->getName());

        return $this;
    }

    public function writeTaskArgs(string ...$args): self
    {
        log('Hashing task args "{args}"', 'debug', [
            'args' => implode(', ', $args),
        ]);
        foreach ($args as $arg) {
            if (GlobalHelper::getInput()->hasArgument($arg)) {
                $this->write(GlobalHelper::getInput()->getArgument($arg));
            }
        }

        return $this;
    }

    public function writeTask(): self
    {
        log('Hashing task name "{name}" and args', 'debug', [
            'name' => GlobalHelper::getApplication()->getName(),
        ]);

        $this->writeTaskName();
        foreach (GlobalHelper::getInput()->getArguments() as $name => $value) {
            if (!empty($value)) {
                if (\is_array($value)) {
                    $value = implode(',', $value);
                } else {
                    $value = (string) $value;
                }
                $this->write($value);
            }
        }

        return $this;
    }

    public function finish(): string
    {
        return hash_final($this->hashContext);
    }
}
