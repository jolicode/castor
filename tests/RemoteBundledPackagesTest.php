<?php

namespace Castor\Tests;

use Symfony\Component\Filesystem\Filesystem;

class RemoteBundledPackagesTest extends TaskTestCase
{
    private const string FIXTURES = __DIR__ . '/fixtures/valid';

    public function testAPackageIncompatibleWithTheBundledVersionFailsAtInstallTime(): void
    {
        $process = $this->runTask(['hello'], '{{ base }}/tests/fixtures/valid/remote-bundled-conflict', needRemote: true, needResetVendor: true);

        $this->assertNotSame(0, $process->getExitCode());

        $errorOutput = $process->getErrorOutput();
        $this->assertStringContainsString('requires nikic/php-parser ^4.0', $errorOutput);
        $this->assertStringContainsString('which already provides nikic/php-parser (v', $errorOutput);
        $this->assertStringContainsString('castor composer show --self', $errorOutput);
        $this->assertStringContainsString('extra.castor.replace-bundled-packages', $errorOutput);
        $this->assertFileDoesNotExist(self::FIXTURES . '/remote-bundled-conflict/castor.composer.lock');
    }

    public function testABundledPackageIsNotInstalledASecondTime(): void
    {
        $process = $this->runTask(['hello'], '{{ base }}/tests/fixtures/valid/remote-bundled-compatible', needRemote: true, needResetVendor: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the fixture', $process->getOutput());
        $this->assertFileExists(self::FIXTURES . '/remote-bundled-compatible/.castor/vendor/autoload.php');
        $this->assertDirectoryDoesNotExist(self::FIXTURES . '/remote-bundled-compatible/.castor/vendor/psr');
    }

    public function testTheBundledPackagesAreInstalledAnywayWhenOptingOut(): void
    {
        $directory = $this->copyFixture('remote-bundled-compatible');

        try {
            $composerJsonFile = $directory . '/castor.composer.json';
            $composerJson = json_decode((string) file_get_contents($composerJsonFile), true, 512, \JSON_THROW_ON_ERROR);
            $composerJson['extra']['castor']['replace-bundled-packages'] = false;
            file_put_contents($composerJsonFile, json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
            // The committed lock was resolved with the bundled packages
            unlink($directory . '/castor.composer.lock');

            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('Hello from the fixture', $process->getOutput());
            $this->assertDirectoryExists($directory . '/.castor/vendor/psr/log');
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function testALockListingANowBundledPackageIsMigrated(): void
    {
        $directory = $this->copyFixture('remote-bundled-migration');

        try {
            $lockFile = $directory . '/castor.composer.lock';
            $this->assertSame(['psr/log'], $this->getLockedPackages($lockFile));

            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('Removed from castor.composer.lock, as bundled with Castor: psr/log', $process->getOutput());
            $this->assertStringContainsString('Hello from the fixture', $process->getOutput());
            $this->assertSame([], $this->getLockedPackages($lockFile));
            $this->assertDirectoryDoesNotExist($directory . '/.castor/vendor/psr');

            // Installed for good: the next run leaves the packages alone
            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringNotContainsString('Downloading remote packages', $process->getOutput());
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    private function copyFixture(string $name): string
    {
        $directory = sys_get_temp_dir() . '/castor-tests/' . $name . '-' . uniqid();

        $fs = new Filesystem();
        $fs->mirror(self::FIXTURES . '/' . $name, $directory);
        $fs->remove($directory . '/.castor/vendor');

        return $directory;
    }

    /**
     * @return list<string>
     */
    private function getLockedPackages(string $lockFile): array
    {
        $lock = json_decode((string) file_get_contents($lockFile), true, 512, \JSON_THROW_ON_ERROR);

        return array_column($lock['packages'], 'name');
    }
}
