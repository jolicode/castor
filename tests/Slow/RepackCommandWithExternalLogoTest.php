<?php

namespace Castor\Tests\Slow;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RepackCommandWithExternalLogoTest extends AbstractRepackCommandTest
{
    public function test()
    {
        $finder = new ExecutableFinder();
        $box = $finder->find('box');

        if (null === $box) {
            $this->markTestSkipped('box is not installed.');
        }

        $castorAppDirPath = self::setupRepackedCastorApp('castor-test-repack-with-external-logo');
        $phar = $castorAppDirPath . '/my-app.linux-amd64.phar';

        (new Process([
            self::$castorBin,
            'repack',
            '--os', 'linux',
            '--logo-file', 'simple-logo-file.php',
        ], cwd: $castorAppDirPath))->mustRun();

        $this->assertFileExists($phar);

        $p = (new Process([$phar], cwd: $castorAppDirPath))->mustRun();
        $this->assertStringStartsWith('My LOGO', $p->getOutput());

        (new Process([
            self::$castorBin,
            'repack',
            '--os', 'linux',
            '--logo-file', 'closure-logo-file.php',
        ], cwd: $castorAppDirPath))->mustRun();

        $this->assertFileExists($phar);

        $p = (new Process([$phar], cwd: $castorAppDirPath))->mustRun();
        $this->assertStringStartsWith('/!\ This Special LOGO for my-app in version 1.0.0', $p->getOutput());
    }
}
