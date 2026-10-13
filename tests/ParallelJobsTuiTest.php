<?php

namespace Castor\Tests;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

/**
 * Runs the named jobs of parallel() in a pseudo terminal, where they are shown
 * with symfony/tui, and checks what is left on the screen.
 */
class ParallelJobsTuiTest extends TaskTestCase
{
    private const COLUMNS = 100;
    private const LINES = 50;

    public function testTheBlocksOfTheJobsAreLeftOnTheScreen(): void
    {
        $screen = $this->runInTerminal(['success'], 0);

        $this->assertMatchesRegularExpression('{^✔ lint  \d+\.\ds\n✔ test  \d+\.\ds$}m', $screen);
        $this->assertStringNotContainsString('Linting', $screen);
    }

    public function testTheFullOutputOfAFailedJobIsPrintedAtTheEnd(): void
    {
        $screen = $this->runInTerminal(['failure'], 1);

        $this->assertMatchesRegularExpression('{^✔ lint  \d+\.\ds\n✘ test  failed after \d+\.\ds$}m', $screen);
        // Logged inside the job, the warning is shown with its output
        $this->assertMatchesRegularExpression('{^ test failed, its full output:\n[\d:]+ WARNING +\[castor\] The tests are slow\.\nTest 1: OK\nTest 2: KO$}m', $screen);
        $this->assertStringContainsString('[ERROR] The following process did not finish successfully (exit code 1):', $screen);
        $this->assertStringNotContainsString('Linted', $screen);
        $this->assertSame(1, preg_match_all('{^failure$}m', $screen), 'The usage of the command is printed once.');
    }

    /**
     * Two displays would redraw over each other.
     */
    public function testJobsStartedAtTheSameTimeAsOthersArePrefixed(): void
    {
        $screen = $this->runInTerminal(['siblings'], 0);

        foreach (['a', 'b', 'c', 'd'] as $name) {
            $this->assertSame(1, substr_count($screen, "[{$name}] From {$name}"), "The output of {$name} is shown once.");
            $this->assertMatchesRegularExpression("{^\\[{$name}\\] ✔ Done in \\d+\\.\\ds$}m", $screen);
        }
    }

    public function testWhatAJobEchoesIsCaptured(): void
    {
        $screen = $this->runInTerminal(['echoing'], 1);

        $this->assertMatchesRegularExpression('{^✘ lint  failed after \d+\.\ds\n    Echoed$}m', $screen);
        $this->assertSame(2, substr_count($screen, 'Echoed'), 'What the job echoes is only in its block and in its full output.');
    }

    public function testNothingIsDrawnInQuietMode(): void
    {
        $screen = $this->runInTerminal(['--quiet', 'success'], 0);

        // The output of the commands is still shown, like when not in a job
        $this->assertSame("[lint] Linting\n[test] Testing", $screen);
    }

    public function testTheBlocksAreEndedWhenCastorIsInterrupted(): void
    {
        $output = $this->runInTerminal(['slow'], null, interruptAfter: 1);

        $this->assertMatchesRegularExpression('{^\S lint  \d+\.\ds\n    Linting$}mu', $output);
        $this->assertStringEndsWith("\n", $output, 'The prompt of the shell does not follow the last line.');
    }

    /**
     * @param list<string> $args
     */
    private function runInTerminal(array $args, ?int $expectedExitCode, ?int $interruptAfter = null): string
    {
        if ('Linux' !== \PHP_OS_FAMILY || null === $script = new ExecutableFinder()->find('script')) {
            $this->markTestSkipped('The "script" command of util-linux is needed to run Castor in a pseudo terminal.');
        }

        $command = [self::$castorBin, ...$args];
        if ($interruptAfter) {
            $command = ['timeout', '--signal=INT', (string) $interruptAfter, ...$command];
        }

        $process = new Process(
            [$script, '--quiet', '--return', '--command', implode(' ', array_map(escapeshellarg(...), $command)), '/dev/null'],
            cwd: __DIR__ . '/fixtures/valid/parallel-jobs',
            env: [
                // The display is not used in CI, and needs colors
                'CI' => false,
                'COLUMNS' => self::COLUMNS,
                'CASTOR_CACHE_DIR' => self::$castorCacheDir,
                'CASTOR_DISABLE_AGENT_DETECTION' => 'true',
                'CASTOR_NO_REMOTE' => 1,
                'CASTOR_TEST' => 'true',
                'LINES' => self::LINES,
                'NO_COLOR' => false,
                'TERM' => 'xterm-256color',
            ],
        );
        $process->run();

        if (null !== $expectedExitCode) {
            $this->assertSame($expectedExitCode, $process->getExitCode(), $process->getOutput());
        }

        $screen = new ScreenBuffer(self::COLUMNS, self::LINES);
        $screen->write($process->getOutput());
        $text = implode("\n", [...$screen->getScrollback(), $screen->getScreen()]);

        // Keeps the end of the output, to check where the cursor was left
        return $interruptAfter ? $text . (str_ends_with($process->getOutput(), "\r\n") ? "\n" : '') : $text;
    }
}
