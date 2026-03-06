<?php

namespace Castor\Tests\Slow;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class CompileCommandTest extends TaskTestCase
{
    public function test()
    {
        $finder = new ExecutableFinder();
        $box = $finder->find('box');

        if (null === $box) {
            $this->markTestSkipped('box is not installed.');
        }

        $castorAppDirPath = RepackCommandTest::setupRepackedCastorApp('castor-test-compile');

        $process = new Process(
            [
                self::$castorBin,
                'repack',
                '--os', 'linux',
            ],
            cwd: $castorAppDirPath
        );
        if ($_SERVER['GITHUB_TOKEN'] ?? false) {
            $process->setEnv(['GITHUB_TOKEN' => $_SERVER['GITHUB_TOKEN']]);
        }
        $process->mustRun();

        $binary = $castorAppDirPath . '/castor';

        // If you update this command, you must also update the command in .github/actions/cache/action.yaml
        (new Process(
            [
                self::$castorBin,
                'compile', $castorAppDirPath . '/my-app.linux-amd64.phar',
                '--os', 'linux',
                '--binary-path', $binary,
                '--php-extensions', 'filter,mbstring,phar,posix,tokenizer',
                '--php-ini-file', 'php.ini',
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

        // Test php.ini
        $p = (new Process([$binary, 'timezone'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame('Arctic/Longyearbyen', $p->getOutput());
    }
}
