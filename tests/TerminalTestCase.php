<?php

namespace Castor\Tests;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

/**
 * Runs Castor in a pseudo terminal, where the jobs are shown with
 * symfony/tui, and returns what is left on the screen.
 */
abstract class TerminalTestCase extends TaskTestCase
{
    protected const COLUMNS = 100;
    protected const LINES = 50;

    /**
     * @param list<string> $args
     */
    protected function runInTerminal(string $fixture, array $args, ?int $expectedExitCode, ?int $interruptAfter = null, int $lines = self::LINES): string
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
            cwd: __DIR__ . '/fixtures/valid/' . $fixture,
            env: [
                // The display is not used in CI, and needs colors
                'CI' => false,
                'COLUMNS' => self::COLUMNS,
                'CASTOR_CACHE_DIR' => self::$castorCacheDir,
                'CASTOR_DISABLE_AGENT_DETECTION' => 'true',
                'CASTOR_NO_REMOTE' => 1,
                'CASTOR_TEST' => 'true',
                'LINES' => $lines,
                'NO_COLOR' => false,
                'TERM' => 'xterm-256color',
            ],
        );
        $process->run();

        if (null !== $expectedExitCode) {
            $this->assertSame($expectedExitCode, $process->getExitCode(), $process->getOutput());
        }

        $screen = new ScreenBuffer(self::COLUMNS, $lines);
        $screen->write($process->getOutput());
        // The blocks are padded to the width of the screen
        $text = rtrim(implode("\n", array_map(rtrim(...), [...$screen->getScrollback(), ...explode("\n", $screen->getScreen())])));

        // Keeps the end of the output, to check where the cursor was left
        return $interruptAfter ? $text . (str_ends_with($process->getOutput(), "\r\n") ? "\n" : '') : $text;
    }
}
