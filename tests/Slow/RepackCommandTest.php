<?php

namespace Castor\Tests\Slow;

use Castor\Tests\TaskTestCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RepackCommandTest extends TaskTestCase
{
    public function test()
    {
        $finder = new ExecutableFinder();
        $box = $finder->find('box');

        if (null === $box) {
            $this->markTestSkipped('box is not installed.');
        }

        $castorAppDirPath = RepackHelper::setupRepackedCastorApp('castor-test-repack');

        $phar = RepackHelper::repackApp(self::$castorBin, $castorAppDirPath);

        $this->assertFileExists($phar);

        (new Process([$phar], cwd: $castorAppDirPath))->mustRun();

        $p = (new Process([$phar, 'hello'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Twice, because we want to be sure the phar is not corrupted after a
        // run
        $p = (new Process([$phar, 'hello'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Test remote
        $p = (new Process([$phar, 'pyrech:hello-example'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame("\nHello from example!\n===================\n\n", $p->getOutput());

        // Ensure the Root is well set
        $p = (new Process([$phar, 'ls'], cwd: $castorAppDirPath))->mustRun();
        $this->assertEquals('my-app.linux-amd64.phar', trim($p->getOutput()));
    }
}
