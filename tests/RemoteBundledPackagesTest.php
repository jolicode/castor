<?php

namespace Castor\Tests;

use Composer\InstalledVersions;
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
        $this->assertStringContainsString('from the packages bundled with Castor v', $errorOutput);
        $this->assertStringContainsString('has higher repository priority', $errorOutput);
        $this->assertStringContainsString('extra.castor.bundled-packages', $errorOutput);
        $this->assertFileDoesNotExist(self::FIXTURES . '/remote-bundled-conflict/castor.composer.lock');
    }

    public function testABundledPackageIsNotInstalledASecondTime(): void
    {
        $process = $this->runTask(['hello'], '{{ base }}/tests/fixtures/valid/remote-bundled-compatible', needRemote: true, needResetVendor: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the fixture', $process->getOutput());
        $this->assertFileExists(self::FIXTURES . '/remote-bundled-compatible/.castor/vendor/autoload.php');
        $this->assertDirectoryDoesNotExist(self::FIXTURES . '/remote-bundled-compatible/.castor/vendor/psr');
        // Locked as the metapackage of the shipped version
        $this->assertSame(['psr/log' => ['metapackage', InstalledVersions::getPrettyVersion('psr/log')]], $this->getLockedPackages(self::FIXTURES . '/remote-bundled-compatible/castor.composer.lock'));
    }

    public function testTheBundledPackagesAreInstalledAnywayWhenOptingOut(): void
    {
        $directory = $this->copyFixture('remote-bundled-compatible');

        try {
            $composerJsonFile = $directory . '/castor.composer.json';
            $composerJson = json_decode((string) file_get_contents($composerJsonFile), true, 512, \JSON_THROW_ON_ERROR);
            $composerJson['extra']['castor']['bundled-packages'] = false;
            file_put_contents($composerJsonFile, json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
            // The committed lock was resolved with the bundled packages
            unlink($directory . '/castor.composer.lock');

            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('Hello from the fixture', $process->getOutput());
            $this->assertDirectoryExists($directory . '/.castor/vendor/psr/log');
            $this->assertSame('library', $this->getLockedPackages($directory . '/castor.composer.lock')['psr/log'][0]);
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function testALockWrittenBeforeThePackageWasBundledIsMigrated(): void
    {
        $directory = $this->copyFixture('remote-bundled-migration');

        try {
            $lockFile = $directory . '/castor.composer.lock';
            $this->assertSame('library', $this->getLockedPackages($lockFile)['psr/log'][0]);

            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('Updated in castor.composer.lock to follow the packages bundled with Castor: psr/log', $process->getOutput());
            $this->assertStringContainsString('Hello from the fixture', $process->getOutput());
            $this->assertSame(['psr/log' => ['metapackage', InstalledVersions::getPrettyVersion('psr/log')]], $this->getLockedPackages($lockFile));
            $this->assertDirectoryDoesNotExist($directory . '/.castor/vendor/psr');

            // Installed for good: the next run leaves the packages alone
            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringNotContainsString('Downloading remote packages', $process->getOutput());
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function testALockWrittenByAnotherCastorVersionIsLeftAloneWhileTheConstraintsHold(): void
    {
        $directory = $this->copyFixture('remote-bundled-compatible');

        try {
            // As if the lock was written by a Castor shipping another version,
            // still matching the "^3.0" of castor.composer.json
            $lockFile = $directory . '/castor.composer.lock';
            $lock = json_decode((string) file_get_contents($lockFile), true, 512, \JSON_THROW_ON_ERROR);
            $lock['packages'][0]['version'] = '3.0.0';
            file_put_contents($lockFile, json_encode($lock, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringNotContainsString('Updated in castor.composer.lock', $process->getOutput());
            $this->assertSame(['psr/log' => ['metapackage', '3.0.0']], $this->getLockedPackages($lockFile));
            $this->assertDirectoryDoesNotExist($directory . '/.castor/vendor/psr');
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function testALockWrittenByAnotherCastorVersionIsUpdatedWhenAConstraintBreaks(): void
    {
        $directory = $this->copyFixture('remote-bundled-compatible');

        try {
            // As if the lock was written by a Castor shipping the exact version
            // castor.composer.json requires, which the running one does not ship
            $composerJsonFile = $directory . '/castor.composer.json';
            $composerJson = json_decode((string) file_get_contents($composerJsonFile), true, 512, \JSON_THROW_ON_ERROR);
            $composerJson['require']['psr/log'] = '3.0.0';
            file_put_contents($composerJsonFile, json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
            $lockFile = $directory . '/castor.composer.lock';
            $lock = json_decode((string) file_get_contents($lockFile), true, 512, \JSON_THROW_ON_ERROR);
            $lock['packages'][0]['version'] = '3.0.0';
            file_put_contents($lockFile, json_encode($lock, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertNotSame(0, $process->getExitCode());
            $errorOutput = $process->getErrorOutput();
            $this->assertStringContainsString('requires psr/log 3.0.0', $errorOutput);
            $this->assertStringContainsString('from the packages bundled with Castor v', $errorOutput);
            $this->assertStringContainsString('extra.castor.bundled-packages', $errorOutput);
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function testAPackageTheRunningCastorNoLongerShipsIsInstalledForReal(): void
    {
        $directory = $this->copyFixture('remote-bundled-compatible');

        try {
            // A package Castor does not ship, resolved for real first
            $composerJsonFile = $directory . '/castor.composer.json';
            $composerJson = json_decode((string) file_get_contents($composerJsonFile), true, 512, \JSON_THROW_ON_ERROR);
            $composerJson['require']['psr/http-message'] = '^2.0';
            file_put_contents($composerJsonFile, json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
            $lockFile = $directory . '/castor.composer.lock';
            unlink($lockFile);

            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame('library', $this->getLockedPackages($lockFile)['psr/http-message'][0]);

            // As if the lock was written by a Castor that shipped it: a
            // metapackage, with no files behind
            $lock = json_decode((string) file_get_contents($lockFile), true, 512, \JSON_THROW_ON_ERROR);
            foreach ($lock['packages'] as $i => $package) {
                if ('psr/http-message' === $package['name']) {
                    $lock['packages'][$i] = [
                        'name' => $package['name'],
                        'version' => $package['version'],
                        'type' => 'metapackage',
                        'extra' => ['castor' => ['bundled' => true]],
                    ];
                }
            }
            file_put_contents($lockFile, json_encode($lock, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
            new Filesystem()->remove($directory . '/.castor/vendor');

            $process = $this->runTask(['hello'], $directory, needRemote: true);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('Updated in castor.composer.lock to follow the packages bundled with Castor: psr/http-message', $process->getOutput());
            $this->assertSame('library', $this->getLockedPackages($lockFile)['psr/http-message'][0]);
            $this->assertDirectoryExists($directory . '/.castor/vendor/psr/http-message');
            $this->assertDirectoryDoesNotExist($directory . '/.castor/vendor/psr/log');
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
     * @return array<string, array{string, string}> package name => [type, version]
     */
    private function getLockedPackages(string $lockFile): array
    {
        $lock = json_decode((string) file_get_contents($lockFile), true, 512, \JSON_THROW_ON_ERROR);
        $packages = [];

        foreach ($lock['packages'] as $package) {
            $packages[$package['name']] = [$package['type'], $package['version']];
        }

        return $packages;
    }
}
