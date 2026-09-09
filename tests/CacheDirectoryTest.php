<?php

namespace Castor\Tests;

use Symfony\Component\Process\Exception\ProcessFailedException;

class CacheDirectoryTest extends TaskTestCase
{
    public function testTheCacheDirectoryIsOnlyReadableByItsOwner(): void
    {
        if (\DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('File modes are not supported on Windows.');
        }

        // runTask() removes the cache directory first, so Castor creates it
        $process = $this->runTask(['list']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertDirectoryExists(self::$castorCacheDir);
        $this->assertSame(0o700, fileperms(self::$castorCacheDir) & 0o777);

        // An existing, too permissive, directory is restricted
        chmod(self::$castorCacheDir, 0o755);
        $process = $this->runTask(['list'], needResetCache: false);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertSame(0o700, fileperms(self::$castorCacheDir) & 0o777);
    }
}
