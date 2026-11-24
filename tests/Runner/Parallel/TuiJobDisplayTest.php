<?php

namespace Castor\Tests\Runner\Parallel;

use Castor\Runner\Parallel\TuiJobDisplay;
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
        $stream = fopen('php://memory', 'w+') ?: throw new \RuntimeException('Could not open a memory stream.');
        $output = new StreamOutput($stream, decorated: true);
        $display = new TuiJobDisplay($output, $output, ['latin', 'other']);

        try {
            $display->getJob('latin')->start();
            $display->getJob('other')->start();
            $display->getJob('latin')->output->write("cr\xE8me caf\xE9\n\e[2J\e[5;5HMoved\e]0;Title\\a\n\e[32mGreen\e[39m\n");
            $display->tick();

            $screen = new ScreenBuffer(self::COLUMNS, self::LINES);
            $screen->write((string) stream_get_contents($stream, offset: 0));

            $this->assertMatchesRegularExpression('{^\S latin  \d+\.\ds\n    cr\?me caf\?\n    Moved\n    Green\n\S other  \d+\.\ds$}u', $screen->getScreen());
        } finally {
            foreach (['latin', 'other'] as $name) {
                $display->getJob($name)->finish();
                $display->finish($display->getJob($name));
            }
            $display->stop();
        }
    }

    public function testTheStatusOfAJobIsShownNextToItsName(): void
    {
        $stream = fopen('php://memory', 'w+') ?: throw new \RuntimeException('Could not open a memory stream.');
        $output = new StreamOutput($stream, decorated: true);
        $display = new TuiJobDisplay($output, $output, ['web1', 'web2']);

        try {
            $display->getJob('web1')->start();
            $display->getJob('web1')->setStatus("installing\n62 %");
            $display->getJob('web2')->start();
            $display->getJob('web2')->setStatus('upgraded');
            $display->getJob('web2')->finish();
            $display->finish($display->getJob('web2'));
            $display->tick();

            $screen = new ScreenBuffer(self::COLUMNS, self::LINES);
            $screen->write((string) stream_get_contents($stream, offset: 0));

            $this->assertMatchesRegularExpression('{^\S web1  installing 62 %  \d+\.\ds\n✔ web2  upgraded  \d+\.\ds$}u', $screen->getScreen());
        } finally {
            $display->getJob('web1')->finish();
            $display->finish($display->getJob('web1'));
            $display->stop();
        }
    }

    public function testAJobCanBeMarkedAsFailedOrSkipped(): void
    {
        $stream = fopen('php://memory', 'w+') ?: throw new \RuntimeException('Could not open a memory stream.');
        $output = new StreamOutput($stream, decorated: true);
        $display = new TuiJobDisplay($output, $output, ['web1', 'web2']);

        $display->getJob('web1')->start();
        $display->getJob('web1')->setStatus('unreachable');
        $display->getJob('web1')->markAsFailed();
        $display->getJob('web2')->start();
        $display->getJob('web2')->setStatus('skipped');
        $display->getJob('web2')->markAsSkipped();
        foreach (['web1', 'web2'] as $name) {
            $display->getJob($name)->finish();
            $display->finish($display->getJob($name));
        }
        $display->stop();

        $screen = new ScreenBuffer(self::COLUMNS, self::LINES);
        $screen->write((string) stream_get_contents($stream, offset: 0));

        $this->assertMatchesRegularExpression('{^✘ web1  unreachable  failed after \d+\.\ds\n- web2  skipped  \d+\.\ds$}u', trim($screen->getScreen()));
        $this->assertStringNotContainsString('its full output', $screen->getScreen(), 'A job that printed nothing has no output to print again.');
    }

    /**
     * The jobs waiting for their turn, and the finished ones, do not need
     * any line under their name.
     */
    public function testTheLinesOfTheTerminalAreSharedBetweenTheRunningJobs(): void
    {
        $stream = fopen('php://memory', 'w+') ?: throw new \RuntimeException('Could not open a memory stream.');
        $output = new StreamOutput($stream, decorated: true);
        $names = array_map(static fn (int $i) => "web{$i}", range(1, 8));
        $display = new TuiJobDisplay($output, $output, $names);

        try {
            foreach (['web1', 'web2'] as $name) {
                $display->getJob($name)->start();
                $display->getJob($name)->output->write("{$name} 1\n{$name} 2\n{$name} 3\n{$name} 4\n{$name} 5\n{$name} 6\n");
            }
            // Its last lines stay under its name
            $display->getJob('web1')->finish(new \RuntimeException('boom'));
            $display->finish($display->getJob('web1'));
            $display->tick();

            $screen = new ScreenBuffer(self::COLUMNS, self::LINES);
            $screen->write((string) stream_get_contents($stream, offset: 0));

            // 20 lines: one is left free, 8 hold the names, and 11 are shared.
            // web1 got 5 of them when both were running, web2 gets 5 of the 6 left.
            $this->assertMatchesRegularExpression(
                '{^✘ web1  failed after \d+\.\ds\n(    web1 [2-6]\n){5}\S web2  \d+\.\ds\n(    web2 [2-6]\n){5}· web3  waiting\n}u',
                $screen->getScreen(),
            );
        } finally {
            foreach ($names as $name) {
                $display->getJob($name)->finish();
                $display->finish($display->getJob($name));
            }
            $display->stop();
        }
    }

    public function testAJobIsShownAsWaitingUntilItStarts(): void
    {
        $stream = fopen('php://memory', 'w+') ?: throw new \RuntimeException('Could not open a memory stream.');
        $output = new StreamOutput($stream, decorated: true);
        $display = new TuiJobDisplay($output, $output, ['web1', 'web2']);

        try {
            $display->getJob('web1')->start();
            $display->tick();

            $screen = new ScreenBuffer(self::COLUMNS, self::LINES);
            $screen->write((string) stream_get_contents($stream, offset: 0));

            $this->assertMatchesRegularExpression('{^[^·\s] web1  \d+\.\ds\n· web2  waiting$}u', $screen->getScreen());
        } finally {
            foreach (['web1', 'web2'] as $name) {
                $display->getJob($name)->finish();
                $display->finish($display->getJob($name));
            }
            $display->stop();
        }
    }
}
