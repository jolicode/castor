<?php

namespace Castor\Tests\Slow;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RepackCommandWithExternalLogoTest extends TaskTestCase
{
    protected function setUp(): void
    {
        $finder = new ExecutableFinder();
        $box = $finder->find('box');

        if (null === $box) {
            $this->markTestSkipped('box is not installed.');
        }
    }

    public function testWithLogoAsFile(): void
    {
        $castorAppDirPath = RepackHelper::setupRepackedCastorApp('castor-test-repack-with-external-logo-file');
        $fs = new Filesystem();
        $fs->dumpFile($castorAppDirPath . '/simple-logo-file.php', <<<'SIMPLELOGOFILE'
            <?php
            return 'My LOGO';
            SIMPLELOGOFILE
        );

        $phar = RepackHelper::repackApp(self::$castorBin, $castorAppDirPath, ['--logo-file', 'simple-logo-file.php']);

        $this->assertFileExists($phar);

        $p = new Process([$phar], cwd: $castorAppDirPath)->mustRun();
        $this->assertStringStartsWith('My LOGO', $p->getOutput());
    }

    public function testWithLogoAsClosure(): void
    {
        $castorAppDirPath = RepackHelper::setupRepackedCastorApp('castor-test-repack-with-external-logo-closure');
        $fs = new Filesystem();
        $fs->dumpFile($castorAppDirPath . '/closure-logo-file.php', <<<'CLOSURELOGOFILE'
            <?php
            return function (string $appName, string $appVersion) {
                return '/!\ This Special LOGO for ' . $appName . ' in version ' . $appVersion;
            };
            CLOSURELOGOFILE
        );

        $phar = RepackHelper::repackApp(self::$castorBin, $castorAppDirPath, ['--logo-file', 'closure-logo-file.php']);

        $this->assertFileExists($phar);

        $p = new Process([$phar], cwd: $castorAppDirPath)->mustRun();
        $this->assertStringStartsWith('/!\ This Special LOGO for my-app in version 1.0.0', $p->getOutput());
    }
}
