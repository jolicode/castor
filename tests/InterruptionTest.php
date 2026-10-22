<?php

namespace Castor\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A CTRL+C stops the task, even when the failures are allowed.
 */
class InterruptionTest extends TaskTestCase
{
    public function testAProcessInterruptedInTheTerminalStopsTheTask(): void
    {
        $output = $this->runInTerminal(['terminal']);

        $this->assertStringContainsString('[WARNING] The command "exit 130" has been interrupted.', $output);
        $this->assertStringNotContainsString('Next step', $output);
    }

    public function testExitCodeDoesNotHideAnInterruption(): void
    {
        $output = $this->runInTerminal(['terminal-exit-code']);

        $this->assertStringContainsString('has been interrupted.', $output);
        $this->assertStringNotContainsString('Next step', $output);
    }

    public function testTheTaskGoesOnWhenSigintIsTrapped(): void
    {
        $output = $this->runInTerminal(['terminal-trapped'], 0);

        $this->assertStringContainsString('Next step', $output);
    }

    public function testTheExitCode130IsAFailureLikeAnyOtherWithoutTerminal(): void
    {
        $process = $this->runTask(['no-terminal'], '{{ base }}/tests/fixtures/valid/interruption');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Next step', $process->getOutput());
    }

    public function testAProcessKilledBySigintStopsTheTask(): void
    {
        $process = $this->runTask(['signaled'], '{{ base }}/tests/fixtures/valid/interruption');

        $this->assertSame(130, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('[WARNING] The command "kill -INT $$" has been interrupted.', $process->getOutput());
        $this->assertStringNotContainsString('Next step', $process->getOutput());
    }

    // Done by Symfony Console itself since 8.2
    #[DataProvider('provideSignals')]
    public function testCastorStoppedByASignalExitsWith128PlusTheSignalNumber(string $signal, int $expectedExitCode): void
    {
        if (!\function_exists('pcntl_signal')) {
            $this->markTestSkipped('The pcntl extension is needed to handle the signals.');
        }

        $process = $this->runTask(['castor-signaled', $signal], '{{ base }}/tests/fixtures/valid/interruption');

        $this->assertSame($expectedExitCode, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringNotContainsString('Next step', $process->getOutput());
    }

    public static function provideSignals(): \Generator
    {
        yield 'SIGINT' => ['INT', 130];
        yield 'SIGTERM' => ['TERM', 143];
        yield 'SIGUSR1' => ['USR1', 138];
    }

    /**
     * @param list<string> $args
     */
    private function runInTerminal(array $args, int $expectedExitCode = 130): string
    {
        if ('Linux' !== \PHP_OS_FAMILY || null === $script = new ExecutableFinder()->find('script')) {
            $this->markTestSkipped('The "script" command of util-linux is needed to run Castor in a pseudo terminal.');
        }

        $process = new Process(
            [$script, '--quiet', '--return', '--command', implode(' ', array_map(escapeshellarg(...), [self::$castorBin, '--no-ansi', ...$args])), '/dev/null'],
            cwd: __DIR__ . '/fixtures/valid/interruption',
            env: [
                'COLUMNS' => 1000,
                'CASTOR_CACHE_DIR' => self::$castorCacheDir,
                'CASTOR_DISABLE_AGENT_DETECTION' => 'true',
                'CASTOR_NO_REMOTE' => 1,
                'CASTOR_TEST' => 'true',
            ],
        );
        $process->run();

        $this->assertSame($expectedExitCode, $process->getExitCode(), $process->getOutput());

        return str_replace("\r\n", "\n", $process->getOutput());
    }
}
