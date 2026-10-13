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
}
