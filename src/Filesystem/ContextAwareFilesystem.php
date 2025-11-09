<?php

namespace Castor\Filesystem;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

readonly class ContextAwareFilesystem
{
    public function __construct(
        private Filesystem $filesystem,
        private string $workingDirectory,
    ) {
    }

    public function copy(string $originFile, string $targetFile, bool $overwriteNewerFiles = false): void
    {
        $this->filesystem->copy(
            $this->resolvePath($originFile),
            $this->resolvePath($targetFile),
            $overwriteNewerFiles
        );
    }

    /**
     * @param string|iterable<string> $dirs
     */
    public function mkdir(string|iterable $dirs, int $mode = 0o777): void
    {
        $this->filesystem->mkdir($this->resolvePaths($dirs), $mode);
    }

    /**
     * @param string|iterable<string> $files
     */
    public function exists(string|iterable $files): bool
    {
        return $this->filesystem->exists($this->resolvePaths($files));
    }

    /**
     * @param string|iterable<string> $files
     */
    public function touch(string|iterable $files, ?int $time = null, ?int $atime = null): void
    {
        $this->filesystem->touch($this->resolvePaths($files), $time, $atime);
    }

    /**
     * @param string|iterable<string> $files
     */
    public function remove(string|iterable $files): void
    {
        $this->filesystem->remove($this->resolvePaths($files));
    }

    /**
     * @param string|iterable<string> $files
     */
    public function chmod(string|iterable $files, int $mode, int $umask = 0o000, bool $recursive = false): void
    {
        $this->filesystem->chmod($this->resolvePaths($files), $mode, $umask, $recursive);
    }

    /**
     * @param string|iterable<string> $files
     */
    public function chown(string|iterable $files, string|int $user, bool $recursive = false): void
    {
        $this->filesystem->chown($this->resolvePaths($files), $user, $recursive);
    }

    /**
     * @param string|iterable<string> $files
     */
    public function chgrp(string|iterable $files, string|int $group, bool $recursive = false): void
    {
        $this->filesystem->chgrp($this->resolvePaths($files), $group, $recursive);
    }

    public function rename(string $origin, string $target, bool $overwrite = false): void
    {
        $this->filesystem->rename(
            $this->resolvePath($origin),
            $this->resolvePath($target),
            $overwrite
        );
    }

    public function symlink(string $originDir, string $targetDir, bool $copyOnWindows = false): void
    {
        $this->filesystem->symlink(
            $this->resolvePath($originDir),
            $this->resolvePath($targetDir),
            $copyOnWindows
        );
    }

    /**
     * @param string|iterable<string> $targetFiles
     */
    public function hardlink(string $originFile, string|iterable $targetFiles): void
    {
        $this->filesystem->hardlink(
            $this->resolvePath($originFile),
            $this->resolvePaths($targetFiles)
        );
    }

    public function readlink(string $path, bool $canonicalize = false): ?string
    {
        return $this->filesystem->readlink($this->resolvePath($path), $canonicalize);
    }

    public function makePathRelative(string $endPath, string $startPath): string
    {
        return $this->filesystem->makePathRelative(
            $this->resolvePath($endPath),
            $this->resolvePath($startPath)
        );
    }

    /**
     * @param \Traversable<mixed>|null $iterator
     * @param array<mixed>             $options
     */
    public function mirror(string $originDir, string $targetDir, ?\Traversable $iterator = null, array $options = []): void
    {
        $this->filesystem->mirror(
            $this->resolvePath($originDir),
            $this->resolvePath($targetDir),
            $iterator,
            $options
        );
    }

    public function isAbsolutePath(string $file): bool
    {
        return $this->filesystem->isAbsolutePath($file);
    }

    public function tempnam(string $dir, string $prefix, string $suffix = ''): string
    {
        return $this->filesystem->tempnam($this->resolvePath($dir), $prefix, $suffix);
    }

    /**
     * @param string|resource $content
     */
    public function dumpFile(string $filename, $content): void
    {
        $this->filesystem->dumpFile($this->resolvePath($filename), $content);
    }

    /**
     * @param string|resource $content
     */
    public function appendToFile(string $filename, $content, bool $lock = false): void
    {
        $this->filesystem->appendToFile($this->resolvePath($filename), $content, $lock);
    }

    public function readFile(string $filename): string
    {
        return $this->filesystem->readFile($this->resolvePath($filename));
    }

    private function resolvePath(string $path): string
    {
        if (Path::isAbsolute($path)) {
            return $path;
        }

        return Path::makeAbsolute($path, $this->workingDirectory);
    }

    /**
     * @param string|iterable<string> $files
     *
     * @return string|array<string>
     */
    private function resolvePaths(string|iterable $files): string|array
    {
        if (\is_string($files)) {
            return $this->resolvePath($files);
        }

        $resolved = [];
        foreach ($files as $file) {
            $resolved[] = $this->resolvePath($file);
        }

        return $resolved;
    }
}
