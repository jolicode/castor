<?php

namespace Castor\Tests\Runner\Job;

use Castor\Runner\Job\Job;
use Castor\Runner\Job\TuiJobDisplay;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

class TuiJobDisplayTest extends TestCase
{
    private const COLUMNS = 60;
    private const LINES = 20;

    private string|false $columns;
    private string|false $lines;

    protected function setUp(): void
    {
        $this->columns = getenv('COLUMNS');
        $this->lines = getenv('LINES');
        putenv('COLUMNS=' . self::COLUMNS);
        putenv('LINES=' . self::LINES);
    }

    protected function tearDown(): void
    {
        putenv(false === $this->columns ? 'COLUMNS' : "COLUMNS={$this->columns}");
        putenv(false === $this->lines ? 'LINES' : "LINES={$this->lines}");
    }

    /**
     * symfony/tui cannot measure invalid UTF-8, and the other escape
     * sequences would move the cursor behind its back.
     */
    public function testTheLastLinesAreCleanedBeforeBeingDrawn(): void
    {
        $stream = $this->openStream();
        $output = new StreamOutput($stream, decorated: true);
        $display = new TuiJobDisplay($output);
        $latin = $this->addJob($display, $output, 'latin');
        $other = $this->addJob($display, $output, 'other');

        try {
            $latin->output->write("cr\xE8me caf\xE9\n\e[2J\e[5;5HMoved\e]0;Title\\a\n\e[32mGreen\e[39m\n");
            $display->tick();

            $this->assertMatchesRegularExpression('{^\S latin  \d+\.\ds\n    cr\?me caf\?\n    Moved\n    Green\n\S other  \d+\.\ds$}u', $this->getScreen($stream));
        } finally {
            $this->endJob($display, $latin);
            $this->endJob($display, $other);
            $display->stop();
        }
    }

    public function testAJobThatRunsAgainReusesItsBlock(): void
    {
        $stream = $this->openStream();
        $output = new StreamOutput($stream, decorated: true);
        $display = new TuiJobDisplay($output);
        $install = $this->addJob($display, $output, 'install');
        $build = $this->addJob($display, $output, 'build');
        $this->endJob($display, $install);
        $this->endJob($display, $build);

        $install->begin();
        $display->update($install);
        $this->endJob($display, $install);
        $display->stop();

        $this->assertMatchesRegularExpression('{^✔ install  \d+\.\ds\n✔ build    \d+\.\ds$}u', $this->getScreen($stream));
    }

    /**
     * The blocks of the finished jobs are left above the output.
     */
    public function testAJobThatRunsAgainAfterSomeOutputGetsANewBlock(): void
    {
        $stream = $this->openStream();
        $output = new StreamOutput($stream, decorated: true);
        $display = new TuiJobDisplay($output);
        $install = $this->addJob($display, $output, 'install');
        $this->endJob($display, $install);

        $display->suspend();
        $output->writeln('Outside of the jobs');
        usleep(300_000);

        $install->begin();
        $display->update($install);
        $this->endJob($display, $install);
        $display->stop();

        $this->assertMatchesRegularExpression('{^✔ install  \d+\.\ds\nOutside of the jobs\n✔ install  \d+\.\ds$}u', $this->getScreen($stream));
    }

    /**
     * The region is redrawn in place, it must fit on the screen.
     */
    public function testTheBlocksOfTheFinishedJobsAreLeftAboveWhenTheScreenIsFull(): void
    {
        $stream = $this->openStream();
        $output = new StreamOutput($stream, decorated: true);
        $display = new TuiJobDisplay($output);

        for ($i = 1; $i <= self::LINES + 5; ++$i) {
            $this->endJob($display, $this->addJob($display, $output, "job {$i}"));
        }
        $display->stop();

        $screen = $this->getScreen($stream);
        for ($i = 1; $i <= self::LINES + 5; ++$i) {
            $this->assertSame(1, preg_match_all("{^✔ job {$i} +\\d+\\.\\ds}m", $screen), "The block of the job {$i} is shown once.");
        }
    }

    private function addJob(TuiJobDisplay $display, StreamOutput $output, string $name): Job
    {
        $job = null;
        $job = new Job(
            $name,
            $output,
            $output,
            static function (Job $job, string $line) use ($display): void {
                $display->addLine($job, $line, false);
            },
            static function (Job $job, string $line) use ($display): void {
                $display->addLine($job, $line, true);
            },
        );
        $display->add($job);
        $job->begin();
        $display->update($job);

        return $job;
    }

    private function endJob(TuiJobDisplay $display, Job $job): void
    {
        $job->end();
        $display->update($job);
    }

    /**
     * @return resource
     */
    private function openStream()
    {
        return fopen('php://memory', 'w+') ?: throw new \RuntimeException('Could not open a memory stream.');
    }

    /**
     * @param resource $stream
     */
    private function getScreen($stream): string
    {
        $screen = new ScreenBuffer(self::COLUMNS, self::LINES);
        $screen->write((string) stream_get_contents($stream, offset: 0));

        return rtrim(implode("\n", array_map(rtrim(...), [...$screen->getScrollback(), ...explode("\n", $screen->getScreen())])));
    }
}
