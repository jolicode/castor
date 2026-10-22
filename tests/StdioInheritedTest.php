<?php

namespace Castor\Tests;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * The process inherits the standard input, output and error of Castor, when
 * nothing reads its output.
 */
class StdioInheritedTest extends TaskTestCase
{
    public function testTheProcessWritesToTheStreamsOfCastor(): void
    {
        $process = $this->runTask(['streams'], '{{ base }}/tests/fixtures/valid/stdio-inherited');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('stdout: not a terminal', $process->getOutput());
        $this->assertStringContainsString('stderr: not a terminal', $process->getErrorOutput());
        $this->assertStringNotContainsString('stderr:', $process->getOutput());
        $this->assertStringContainsString('Captured: ""', $process->getOutput());
    }

    public function testTheProcessSeesTheTerminalOfCastor(): void
    {
        $output = $this->runInTerminal(['streams']);

        $this->assertStringContainsString('stdout: terminal', $output);
        $this->assertStringContainsString('stderr: terminal', $output);
    }

    public function testTheProcessGetsTheSizeOfTheTerminal(): void
    {
        $this->assertStringContainsString('40 150', $this->runInTerminal(['size']));
    }

    public function testTheInputReplacesTheStandardInputOnly(): void
    {
        $output = $this->runInTerminal(['input']);

        $this->assertStringContainsString('from the input (stdout: terminal)', $output);
    }

    public function testTheOutputIsCapturedWhenQuiet(): void
    {
        $process = $this->runTask(['quiet'], '{{ base }}/tests/fixtures/valid/stdio-inherited');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Captured: "foo"', $process->getOutput());
    }

    public function testTheOutputIsGivenToTheCallback(): void
    {
        $process = $this->runTask(['callback'], '{{ base }}/tests/fixtures/valid/stdio-inherited');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Callback: "foo"', $process->getOutput());
    }

    public function testTheOutputOfAJobOfParallelIsCaptured(): void
    {
        $process = $this->runTask(['parallel-job'], '{{ base }}/tests/fixtures/valid/stdio-inherited');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('[job] foo', $process->getOutput());
    }

    public function testTheOutputIsOrganizedInSections(): void
    {
        $output = $this->runInTerminal(['streams'], env: ['CASTOR_USE_SECTION' => 'true']);

        // The PTY is used instead: the standard error stays a pipe
        $this->assertStringContainsString('stdout: terminal', $output);
        $this->assertStringContainsString('stderr: not a terminal', $output);
    }

    public function testACtrlCStopsTheTask(): void
    {
        $output = $this->runInTerminal(['interrupted'], expectedExitCode: 130, ctrlC: true);

        // Like a shell, Castor stops without a word: the CTRL+C has been typed
        $this->assertStringNotContainsString('interrupted', $output);
        $this->assertStringNotContainsString('Next step', $output);
    }

    public function testTheTaskGoesOnWhenTheProcessSurvivesACtrlC(): void
    {
        $output = $this->runInTerminal(['survives'], ctrlC: true);

        $this->assertStringContainsString('caught', $output);
        $this->assertStringContainsString('done', $output);
        $this->assertStringContainsString('Next step', $output);
    }

    public function testTheExitCode130StopsTheTask(): void
    {
        $process = $this->runTask(['exit130'], '{{ base }}/tests/fixtures/valid/stdio-inherited');

        $this->assertSame(130, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringNotContainsString('Next step', $process->getOutput());
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     */
    private function runInTerminal(array $args, int $expectedExitCode = 0, bool $ctrlC = false, array $env = []): string
    {
        if ('Linux' !== \PHP_OS_FAMILY || null === $script = new ExecutableFinder()->find('script')) {
            $this->markTestSkipped('The "script" command of util-linux is needed to run Castor in a pseudo terminal.');
        }

        if ($ctrlC && !\function_exists('pcntl_signal')) {
            $this->markTestSkipped('The pcntl extension is needed to survive a CTRL+C.');
        }

        // What is written to script is typed in the terminal
        $input = new InputStream();

        $process = new Process(
            [$script, '--quiet', '--return', '--command', 'stty rows 40 cols 150; ' . implode(' ', array_map(escapeshellarg(...), [self::$castorBin, '--no-ansi', ...$args])), '/dev/null'],
            cwd: __DIR__ . '/fixtures/valid/stdio-inherited',
            env: [
                'COLUMNS' => 1000,
                'CASTOR_CACHE_DIR' => self::$castorCacheDir,
                'CASTOR_DISABLE_AGENT_DETECTION' => 'true',
                'CASTOR_NO_REMOTE' => 1,
                'CASTOR_TEST' => 'true',
                ...$env,
            ],
            input: $input,
            timeout: 20,
        );
        $process->start();

        if ($ctrlC) {
            $process->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'ready'));
            $input->write("\x03");
        }

        $input->close();
        $process->wait();

        $this->assertSame($expectedExitCode, $process->getExitCode(), $process->getOutput());

        return str_replace("\r\n", "\n", $process->getOutput());
    }
}
