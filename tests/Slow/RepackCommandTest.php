<?php

namespace Castor\Tests\Slow;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RepackCommandTest extends TaskTestCase
{
    protected function setUp(): void
    {
        $finder = new ExecutableFinder();
        $box = $finder->find('box');

        if (null === $box) {
            $this->markTestSkipped('box is not installed.');
        }
    }

    public function testWithCurrentCode(): void
    {
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

    public function testWithGithubRelease(): void
    {
        $castorAppDirPath = RepackHelper::setupRepackedCastorApp('castor-test-repack');
        $fs = new Filesystem();
        $fs->dumpFile($castorAppDirPath . '/castor.php', <<<'PHP'
            <?php

            use Castor\Attribute\AsTask;

            use function Castor\import;
            use function Castor\io;
            use function Castor\run;

            // Should be very minimal, since we use a fixed Castor version
            // (v1.2.0) that does not have the latest features.
            #[AsTask()]
            function hello(): void
            {
                echo "hello";
            }
            PHP
        );

        $phar = RepackHelper::repackApp(self::$castorBin, $castorAppDirPath, useGithubRelease: true);

        $this->assertFileExists($phar);

        (new Process([$phar], cwd: $castorAppDirPath))->mustRun();

        $p = (new Process([$phar, 'hello'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Twice, because we want to be sure the phar is not corrupted after a
        // run
        $p = (new Process([$phar, 'hello'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());
    }
}
