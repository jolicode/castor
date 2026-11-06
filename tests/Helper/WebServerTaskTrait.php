<?php

namespace Castor\Tests\Helper;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * For the "web" task of tests/fixtures/valid/web-server.
 */
trait WebServerTaskTrait
{
    /**
     * @param list<string>          $command
     * @param array<string, string> $env
     */
    private function assertWebServerTaskServesPhpAndCleansUp(array $command, string $cwd, array $env = []): void
    {
        if (null === $setsid = new ExecutableFinder()->find('setsid')) {
            $this->markTestSkipped('The "setsid" command of util-linux is needed to send a CTRL+C.');
        }

        $fs = new Filesystem();
        $temporaryDirectory = sys_get_temp_dir() . '/castor-test-web-server';
        $fs->remove($temporaryDirectory);
        $fs->mkdir($temporaryDirectory);

        $process = new Process([$setsid, ...$command], $cwd, ['TMPDIR' => $temporaryDirectory, ...$env]);
        $process->start();

        $url = null;
        $process->waitUntil(static function (string $type, string $data) use (&$url): bool {
            $url = preg_match('{^URL: (\S+)$}m', $data, $matches) ? $matches[1] : null;

            return null !== $url;
        });

        $this->assertNotNull($url, $process->getOutput() . $process->getErrorOutput());

        $httpContext = stream_context_create(['http' => ['timeout' => 5]]);
        $this->assertSame('Served by cli-server', file_get_contents($url, context: $httpContext));

        // Like a CTRL+C, to the whole process group: when only the master of the
        // built-in web server gets SIGINT, it waits for its workers forever
        posix_kill(-$process->getPid(), \SIGINT);
        $process->wait();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertFalse(@file_get_contents($url, context: $httpContext), 'The server must be stopped.');
        $this->assertSame([], glob($temporaryDirectory . '/*'), 'The task must remove its temporary files.');

        $fs->remove($temporaryDirectory);
    }
}
