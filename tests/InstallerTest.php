<?php

namespace Castor\Tests;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Runs the installer script against the fake releases served by the test web
 * server, see tests/Helper/fixtures/http/installer/releases.php.
 */
class InstallerTest extends TaskTestCase
{
    private string $dir;
    private string $installer;

    protected function setUp(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('The installer only supports Linux and macOS.');
        }

        $this->dir = sys_get_temp_dir() . '/castor-test-installer';

        $fs = new Filesystem();
        $fs->remove($this->dir);
        $fs->mkdir([$this->dir . '/tmp', $this->dir . '/gh-config']);

        // The only change to the script: the releases come from the test web server
        $script = str_replace(
            'https://github.com/jolicode/castor/releases',
            $_SERVER['ENDPOINT'] . '/installer/releases.php',
            (string) file_get_contents(__DIR__ . '/../installer/bash-installer'),
        );
        $this->assertStringContainsString($_SERVER['ENDPOINT'] . '/installer/releases.php/~versionPath~/', $script);
        $fs->dumpFile($this->installer = $this->dir . '/installer', $script);
    }

    public function testTheBinaryIsInstalledWhenItMatchesTheChecksums(): void
    {
        $process = $this->runInstaller('--version=v9.0.0', '--install-dir=' . $this->dir . '/bin');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $this->assertStringContainsString('The binary matches the SHA256SUMS file of the release', $process->getOutput());
        $this->assertStringContainsString('Provenance not verified', $process->getOutput());
        $this->assertStringContainsString('castor v9.0.0 was installed successfully', $process->getOutput());
        $this->assertTrue(is_executable($this->dir . '/bin/castor'));
        $this->assertNoTemporaryFileLeft();
    }

    public function testTheLatestVersionIsInstalledByDefault(): void
    {
        $process = $this->runInstaller('--install-dir=' . $this->dir . '/bin');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $this->assertStringContainsString('castor v9.0.0 was installed successfully', $process->getOutput());
    }

    public function testTheStaticBinaryIsInstalledWithTheStaticOption(): void
    {
        $process = $this->runInstaller('--static', '--version=v9.0.0', '--install-dir=' . $this->dir . '/bin');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $this->assertMatchesRegularExpression('{Downloading .*/castor\.(linux|darwin)-(amd64|arm64)\.$}m', $process->getOutput());
        $this->assertStringContainsString('castor v9.0.0 was installed successfully', $process->getOutput());
    }

    public function testABinaryNotMatchingTheChecksumsIsRefused(): void
    {
        $process = $this->runInstaller('--version=v9.0.1', '--install-dir=' . $this->dir . '/bin');

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('does not match the SHA256SUMS file of the release', $process->getOutput());
        $this->assertFileDoesNotExist($this->dir . '/bin/castor');
        $this->assertNoTemporaryFileLeft();
    }

    public function testAReleaseWithoutChecksumsIsInstalledWithAWarning(): void
    {
        $process = $this->runInstaller('--version=v8.0.0', '--install-dir=' . $this->dir . '/bin');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $this->assertStringContainsString('this release has no SHA256SUMS file', $process->getOutput());
        $this->assertStringContainsString('castor v8.0.0 was installed successfully', $process->getOutput());
    }

    public function testAnUnknownVersionIsReported(): void
    {
        $process = $this->runInstaller('--version=v7.0.0', '--install-dir=' . $this->dir . '/bin');

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString("version 'v7.0.0' does not seem to exist", $process->getOutput());
        $this->assertFileDoesNotExist($this->dir . '/bin/castor');
        $this->assertNoTemporaryFileLeft();
    }

    /**
     * The documented way to run the installer: piped to bash.
     */
    public function testTheScriptRunsWhenPipedToBash(): void
    {
        $process = new Process(['bash', '-s', '--', '--version=v9.0.0', '--install-dir=' . $this->dir . '/bin'], env: $this->getEnv(), input: file_get_contents($this->installer), timeout: 60);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $this->assertStringContainsString('castor v9.0.0 was installed successfully', $process->getOutput());
    }

    private function runInstaller(string ...$args): Process
    {
        $process = new Process(['bash', $this->installer, ...$args], env: $this->getEnv(), timeout: 60);
        $process->run();

        return $process;
    }

    /**
     * @return array<string, string|false>
     */
    private function getEnv(): array
    {
        return [
            'TMPDIR' => $this->dir . '/tmp',
            // Keep the GitHub CLI unauthenticated: the fake binary has no
            // attestation, so the provenance check must be skipped
            'GH_CONFIG_DIR' => $this->dir . '/gh-config',
            'GH_TOKEN' => false,
            'GITHUB_TOKEN' => false,
        ];
    }

    private function assertNoTemporaryFileLeft(): void
    {
        $this->assertSame([], glob($this->dir . '/tmp/*'), 'The temporary files have been removed.');
    }
}
