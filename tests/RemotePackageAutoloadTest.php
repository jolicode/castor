<?php

namespace Castor\Tests;

use Symfony\Component\Filesystem\Filesystem;

class RemotePackageAutoloadTest extends TaskTestCase
{
    private const string DEPRECATION = 'Loading the tasks, contexts and listeners the package "castor-test/package" autoloads without importing it is deprecated';

    private string $directory;

    protected function setUp(): void
    {
        // Copied, as the path repositories lock the packages to their content
        $this->directory = sys_get_temp_dir() . '/castor-tests/remote-package-autoload-' . uniqid();

        new Filesystem()->mirror(__DIR__ . '/fixtures/valid/remote-package-autoload', $this->directory);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testRequiringAPackageGivesItsFunctions(): void
    {
        $process = $this->runTask(['greet-loudly'], $this->directory . '/required', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('HELLO FROM THE PACKAGE', $process->getOutput());
    }

    public function testTheTasksAPackageAutoloadsAreStillLoadedWithoutImportingIt(): void
    {
        $process = $this->runTask(['package:hello'], $this->directory . '/required', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the package', $process->getOutput());
        $this->assertStringContainsString(self::DEPRECATION, $process->getOutput());
        // It only gives functions
        $this->assertStringNotContainsString('castor-test/functions', $process->getOutput());
    }

    public function testImportingAPackageExposesTheTasksItAutoloads(): void
    {
        $process = $this->runTask(['package:hello'], $this->directory . '/imported', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the package', $process->getOutput());
        $this->assertStringNotContainsString(self::DEPRECATION, $process->getOutput());
    }

    public function testMountingAPackageGivesItsPrefixAndItsWorkingDirectoryToTheTasksItAutoloads(): void
    {
        $process = $this->runTask(['mounted:package:hello'], $this->directory . '/mounted', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the package', $process->getOutput());
        $this->assertStringContainsString('Working directory: ' . realpath($this->directory . '/package') . "\n", $process->getOutput());
        $this->assertStringNotContainsString(self::DEPRECATION, $process->getOutput());
    }

    public function testThePackagesOfACastorPluginAreTheOnesOfItsComposerJsonInItsOwnRepository(): void
    {
        $process = $this->runTask(['package:check'], $this->directory . '/package', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the package, checked', $process->getOutput());
        $this->assertStringNotContainsString(self::DEPRECATION, $process->getOutput());
        // Apart from the ones of its vendor directory, and without its dev requirements, which cannot be installed
        $this->assertFileExists($this->directory . '/package/castor.composer.lock');
        $this->assertFileDoesNotExist($this->directory . '/package/composer.lock');

        $process = $this->runTask(['package:hello'], $this->directory . '/package', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the package', $process->getOutput());
    }
}
