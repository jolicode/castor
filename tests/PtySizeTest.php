<?php

namespace Castor\Tests;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * PHP creates the PTY of a process without a size: Castor gives it the size of
 * its terminal.
 */
class PtySizeTest extends TaskTestCase
{
    public function testACommandGetsTheSizeOfTheTerminal(): void
    {
        $this->assertSame('40 150', $this->runInTerminal(['size']));
    }

    public function testAShellCommandGetsTheSizeOfTheTerminal(): void
    {
        $this->assertSame('40 150', $this->runInTerminal(['size-shell']));
    }

    public function testTheCommandLineIsTheOneAsked(): void
    {
        $this->assertSame("Command line: 'true'", $this->runInTerminal(['command-line']));
    }

    public function testTheExitCodeIsTheOneOfTheCommand(): void
    {
        $this->assertSame('Exit code: 3', $this->runInTerminal(['exit-code']));
    }

    public function testAFailureShowsTheCommandAsked(): void
    {
        $output = $this->runInTerminal(['failure'], 1);

        $this->assertMatchesRegularExpression("{did not finish successfully \\(exit code 1\\): *\n\n'false'\n}", $output);
        $this->assertStringNotContainsString('stty', $output);
    }

    public function testThePtyKeepsNoSizeWithoutTerminal(): void
    {
        if (null === $setsid = new ExecutableFinder()->find('setsid')) {
            $this->markTestSkipped('The "setsid" command of util-linux is needed to run Castor without terminal.');
        }

        // In a new session, Castor has no terminal, even when the tests run in one
        $process = $this->createCastorProcess([$setsid, '--wait', self::$castorBin, '--no-ansi', 'size']);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame('0 0', trim($process->getOutput()));
    }

    /**
     * @param list<string> $args
     */
    private function runInTerminal(array $args, int $expectedExitCode = 0): string
    {
        if ('Linux' !== \PHP_OS_FAMILY || null === $script = new ExecutableFinder()->find('script')) {
            $this->markTestSkipped('The "script" command of util-linux is needed to run Castor in a pseudo terminal.');
        }

        $castor = implode(' ', array_map(escapeshellarg(...), [self::$castorBin, '--no-ansi', ...$args]));
        $process = $this->createCastorProcess([$script, '--quiet', '--return', '--command', "stty rows 40 cols 150; {$castor}", '/dev/null']);
        $process->run();

        $this->assertSame($expectedExitCode, $process->getExitCode(), $process->getOutput());

        return trim(str_replace("\r\n", "\n", $process->getOutput()));
    }

    /**
     * @param list<string> $command
     */
    private function createCastorProcess(array $command): Process
    {
        return new Process(
            $command,
            cwd: __DIR__ . '/fixtures/valid/pty-size',
            env: [
                'CASTOR_CACHE_DIR' => self::$castorCacheDir,
                'CASTOR_DISABLE_AGENT_DETECTION' => 'true',
                'CASTOR_NO_REMOTE' => 1,
                'CASTOR_TEST' => 'true',
            ],
        );
    }
}
