<?php

namespace Castor\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CompileCommandTest extends TestCase
{
    public function test()
    {
        $castorAppDirPath = RepackCommandTest::setupRepackedCastorApp('castor-test-compile');

        (new Process(
            [
                'vendor/jolicode/castor/bin/castor',
                'repack',
                '--os', 'linux',
            ],
            cwd: $castorAppDirPath)
        )->mustRun();

        $binary = $castorAppDirPath . '/castor';

        // If you update this command, you must also update the command in .github/workflows/ci.yml
        (new Process(
            [
                'vendor/jolicode/castor/bin/castor',
                'compile', $castorAppDirPath . '/my-app.linux.phar',
                '--os', 'linux',
                '--output', $binary,
                '--php-extensions', 'mbstring,phar,posix,tokenizer',
                '-vvv',
            ],
            cwd: $castorAppDirPath,
            timeout: 5 * 60)
        )->mustRun();

        $this->assertFileExists($binary);

        (new Process([$binary], cwd: $castorAppDirPath))->mustRun();

        $p = (new Process([$binary, 'hello'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Twice, because we want to be sure the phar is not corrupted after a
        // run
        $p = (new Process([$binary, 'hello'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());
    }
}
