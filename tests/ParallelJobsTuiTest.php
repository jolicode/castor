<?php

namespace Castor\Tests;

/**
 * Runs the named jobs of parallel() in a pseudo terminal, and checks what is
 * left on the screen.
 */
class ParallelJobsTuiTest extends TerminalTestCase
{
    public function testTheBlocksOfTheJobsAreLeftOnTheScreen(): void
    {
        $screen = $this->runInTerminal('parallel-jobs', ['success'], 0);

        $this->assertMatchesRegularExpression('{^✔ lint  \d+\.\ds\n✔ test  \d+\.\ds$}m', $screen);
        $this->assertStringNotContainsString('Linting', $screen);
    }

    public function testTheFullOutputOfAFailedJobIsPrintedAtTheEnd(): void
    {
        $screen = $this->runInTerminal('parallel-jobs', ['failure'], 1);

        $this->assertMatchesRegularExpression('{^✔ lint  \d+\.\ds\n✘ test  failed after \d+\.\ds$}m', $screen);
        // Logged inside the job, the warning is shown with its output
        $this->assertMatchesRegularExpression('{^ test failed, its full output:\n[\d:]+ WARNING +\[castor\] The tests are slow\.\nTest 1: OK\nTest 2: KO$}m', $screen);
        $this->assertStringContainsString('[ERROR] The following process did not finish successfully (exit code 1):', $screen);
        $this->assertStringNotContainsString('Linted', $screen);
        $this->assertSame(1, preg_match_all('{^failure$}m', $screen), 'The usage of the command is printed once.');
    }

    /**
     * A single display shows the jobs of all the parallel() calls.
     */
    public function testJobsStartedAtTheSameTimeAsOthersShareTheDisplay(): void
    {
        $screen = $this->runInTerminal('parallel-jobs', ['siblings'], 0);

        foreach (['a', 'b', 'c', 'd'] as $name) {
            $this->assertSame(1, preg_match_all("{^✔ {$name}  \\d+\\.\\ds$}m", $screen), "The block of {$name} is shown once.");
        }
        $this->assertStringNotContainsString('From', $screen);
    }

    public function testWhatAJobEchoesIsCaptured(): void
    {
        $screen = $this->runInTerminal('parallel-jobs', ['echoing'], 1);

        $this->assertMatchesRegularExpression('{^✘ lint  failed after \d+\.\ds\n    Echoed$}m', $screen);
        $this->assertSame(2, substr_count($screen, 'Echoed'), 'What the job echoes is only in its block and in its full output.');
    }

    public function testNothingIsDrawnInQuietMode(): void
    {
        $screen = $this->runInTerminal('parallel-jobs', ['--quiet', 'success'], 0);

        // The output of the commands is still shown, like when not in a job
        $this->assertSame("[lint] Linting\n[test] Testing", $screen);
    }

    public function testTheBlocksAreEndedWhenCastorIsInterrupted(): void
    {
        $output = $this->runInTerminal('parallel-jobs', ['slow'], null, interruptAfter: 1);

        $this->assertMatchesRegularExpression('{^\S lint  \d+\.\ds\n    Linting$}mu', $output);
        $this->assertStringEndsWith("\n", $output, 'The prompt of the shell does not follow the last line.');
    }
}
