<?php

namespace Castor\Tests;

/**
 * Runs the jobs named by the contexts in a pseudo terminal, and checks what
 * is left on the screen.
 */
class SequentialJobsTuiTest extends TerminalTestCase
{
    public function testAJobThatRunsAgainReusesItsBlock(): void
    {
        $screen = $this->runInTerminal('sequential-jobs', ['reused'], 0);

        $this->assertMatchesRegularExpression('{^✔ install  \d+\.\ds\n✔ build    \d+\.\ds$}', $screen);
    }

    public function testAJobThatRunsAgainAfterSomeOutputGetsANewBlock(): void
    {
        $screen = $this->runInTerminal('sequential-jobs', ['interleaved'], 0);

        $this->assertMatchesRegularExpression('{^✔ install  \d+\.\ds\nBetween the jobs\n✔ install  \d+\.\ds\nOutside of the jobs$}', $screen);
    }

    public function testTheFullOutputOfAFailedJobIsPrintedBeforeTheError(): void
    {
        $screen = $this->runInTerminal('sequential-jobs', ['failure'], 1);

        $this->assertMatchesRegularExpression('{^✔ install  \d+\.\ds\n✘ test     failed after \d+\.\ds\n    Test 1: OK\n    Test 2: KO\n\n test failed, its full output:\nTest 1: OK\nTest 2: KO\n}', $screen);
        $this->assertStringContainsString('[ERROR] The following process did not finish successfully (exit code 1):', $screen);
    }

    public function testWhatAJobEchoesIsCaptured(): void
    {
        $screen = $this->runInTerminal('sequential-jobs', ['echoing'], 0);

        $this->assertMatchesRegularExpression('{^✔ echo  \d+\.\ds$}', $screen);
    }

    public function testTheJobsOfParallelAreShownWithTheJobTheyRunIn(): void
    {
        $screen = $this->runInTerminal('sequential-jobs', ['nested'], 0);

        $this->assertMatchesRegularExpression('{^✔ ci    \d+\.\ds\n✔ lint  \d+\.\ds\n✔ test  \d+\.\ds$}', $screen);
    }

    /**
     * The blocks are redrawn in place: they must fit on the screen.
     */
    public function testTheBlocksAreLeftAboveWhenTheScreenIsFull(): void
    {
        $screen = $this->runInTerminal('sequential-jobs', ['many'], 0, lines: 6);

        $this->assertSame(8, preg_match_all('{^✔ job \d  \d+\.\ds$}m', $screen));
        foreach (range(1, 8) as $i) {
            $this->assertSame(1, substr_count($screen, "✔ job {$i} "), "The block of the job {$i} is shown once.");
        }
    }

    public function testTheLinesArePrefixedOutsideOfATerminal(): void
    {
        $process = $this->runTask(['reused'], __DIR__ . '/fixtures/valid/sequential-jobs');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame(<<<'TXT'
            [install] Installing
            [install] ✔ Done in X.Xs
            [build]   Building
            [build]   ✔ Done in X.Xs
            [install] Updating
            [install] ✔ Done in X.Xs

            TXT, preg_replace('{\d+\.\ds}', 'X.Xs', $process->getOutput()));
    }
}
