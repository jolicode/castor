<?php

namespace Castor\Tests\Slow;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class SelfUpdateCommandTest extends TaskTestCase
{
    // Must match tests/Helper/fixtures/http/self-update/binary.php
    private const string DIR = '/castor-test-self-update';

    public function test(): void
    {
        if (!self::$binary && !str_ends_with(self::$castorBin, '.phar')) {
            $this->markTestSkipped('self-update only supports phar and static installations.');
        }

        $fs = new Filesystem();
        $dir = sys_get_temp_dir() . self::DIR;
        $fs->remove($dir);
        $fs->mkdir($dir);

        // The "new" version served by the fake release is the current binary
        // itself, so it passes the --version check
        $fs->copy(self::$castorBin, $dir . '/castor.new');
        $fs->copy(self::$castorBin, $castor = $dir . '/castor');
        $fs->chmod($castor, 0o755);
        $inode = fileinode($castor);

        $process = $this->runSelfUpdate($castor);
        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $this->assertStringContainsString('to verify the provenance', $process->getOutput());
        $this->assertStringContainsString('to v99.0.0!', $process->getOutput());
        $this->assertNotSame($inode, fileinode($castor), 'The binary has been replaced');
        $this->assertFileEquals(self::$castorBin, $castor);
        $this->assertFileExists($castor . '.backup');
        $this->assertFileDoesNotExist($castor . '.tmp');
        new Process([$castor, '--version'])->mustRun();

        $process = $this->runSelfUpdate($castor, '--rollback');
        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $this->assertFileDoesNotExist($castor . '.backup');
        new Process([$castor, '--version'])->mustRun();

        $process = $this->runSelfUpdate($castor, '--rollback');
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('No backup found', $process->getOutput());

        $fs->remove($dir);
    }

    private function runSelfUpdate(string $castor, string ...$args): Process
    {
        $process = new Process(
            [$castor, 'castor:self-update', '--no-ansi', ...$args],
            cwd: \dirname($castor),
            env: [
                // Make sure gh is not authenticated: the fake release has no
                // attestation, so the provenance check must be skipped
                'GH_CONFIG_DIR' => \dirname($castor) . '/gh-config',
                'GH_TOKEN' => false,
                'GITHUB_TOKEN' => false,
                'CASTOR_RELEASES_URL' => $_SERVER['ENDPOINT'] . '/self-update/release.php',
                'CASTOR_CACHE_DIR' => self::$castorCacheDir,
                'CASTOR_DISABLE_AGENT_DETECTION' => 'true',
                'CASTOR_DISABLE_VERSION_CHECK' => 'true',
                'CASTOR_NO_REMOTE' => 1,
                'CASTOR_TEST' => 'true',
            ],
            timeout: 60,
        );
        $process->run();

        return $process;
    }
}
