<?php

namespace Castor\Tests\Slow;

use Castor\Tests\Helper\ServePhpTaskTrait;
use Castor\Tests\TaskTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class CompileCommandTest extends TaskTestCase
{
    use ServePhpTaskTrait;

    public function test(): void
    {
        $finder = new ExecutableFinder();
        $box = $finder->find('box');

        if (null === $box) {
            $this->markTestSkipped('box is not installed.');
        }

        $castorAppDirPath = RepackHelper::setupRepackedCastorApp('castor-test-compile');

        $phar = RepackHelper::repackApp(self::$castorBin, $castorAppDirPath);

        $binary = $castorAppDirPath . '/castor';

        $this->compile($phar, $binary, $castorAppDirPath);

        $this->assertFileExists($binary);

        new Process([$binary], cwd: $castorAppDirPath)->mustRun();

        $p = new Process([$binary, 'hello'], cwd: $castorAppDirPath)->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Twice, because we want to be sure the phar is not corrupted after a
        // run
        $p = new Process([$binary, 'hello'], cwd: $castorAppDirPath)->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Test php.ini
        $p = new Process([$binary, 'timezone'], cwd: $castorAppDirPath)->mustRun();
        $this->assertSame('Arctic/Longyearbyen', $p->getOutput());
    }

    public function testWithPhpCli(): void
    {
        $finder = new ExecutableFinder();
        $box = $finder->find('box');

        if (null === $box) {
            $this->markTestSkipped('box is not installed.');
        }

        $castorAppDirPath = RepackHelper::setupRepackedCastorApp('castor-test-compile-php-cli');

        new Filesystem()->mirror(__DIR__ . '/../fixtures/valid/serve-php', $castorAppDirPath, options: ['override' => true]);

        $phar = RepackHelper::repackApp(self::$castorBin, $castorAppDirPath);

        $binary = $castorAppDirPath . '/castor';

        $this->compile($phar, $binary, $castorAppDirPath, ['--php-cli']);

        $this->assertServesPhpAndCleansUp([$binary, 'web'], $castorAppDirPath);

        $this->assertFileExists($phar);
        $this->assertStringNotContainsString('.castor-php-cli', (string) file_get_contents($phar), 'The phar given to the compile command must not be modified.');
    }

    /**
     * @param list<string> $extraOptions
     */
    private function compile(string $phar, string $binary, string $cwd, array $extraOptions = []): void
    {
        // If you update this command, you must also update the command in .github/actions/cache/action.yaml
        new Process(
            [
                self::$castorBin,
                'compile', $phar,
                '--os', 'linux',
                '--binary-path', $binary,
                '--php-extensions', 'filter,mbstring,pcntl,phar,posix,tokenizer',
                '--php-ini-file', 'php.ini',
                '-vvv',
                ...$extraOptions,
            ],
            cwd: $cwd,
            // Long enough for a cold cache: a failing job does not save the cache
            timeout: 15 * 60
        )
            ->mustRun()
        ;
    }
}
