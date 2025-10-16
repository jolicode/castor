<?php

namespace Castor\Tests\Slow;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RepackCommandTest extends AbstractRepackCommandTest
{
    public function test()
    {
        $finder = new ExecutableFinder();
        $box = $finder->find('box');

        if (null === $box) {
            $this->markTestSkipped('box is not installed.');
        }

        $castorAppDirPath = self::setupRepackedCastorApp('castor-test-repack');

        (new Process([
            './bin/castor',
            'repack',
            '--os', 'linux',
            '--output-directory', 'build',
        ], cwd: $castorAppDirPath))->mustRun();

        $castorOutputDirPath = $castorAppDirPath . '/build';

        $phar = $castorOutputDirPath . '/my-app.linux.phar';
        $this->assertFileExists($phar);

        (new Process([$phar], cwd: $castorOutputDirPath))->mustRun();

        $p = (new Process([$phar, 'hello'], cwd: $castorOutputDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Twice, because we want to be sure the phar is not corrupted after a
        // run
        $p = (new Process([$phar, 'hello'], cwd: $castorOutputDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Test remote
        $p = (new Process([$phar, 'pyrech:hello-example'], cwd: $castorOutputDirPath))->mustRun();
        $this->assertSame("\nHello from example!\n===================\n\n", $p->getOutput());

        // Ensure the Root is well set
        $p = (new Process([$phar, 'ls'], cwd: $castorOutputDirPath))->mustRun();
        $this->assertEquals('my-app.linux.phar', trim($p->getOutput()));
    }
}
