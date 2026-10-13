<?php

namespace Castor\Tests\Runner\Parallel;

use Castor\Runner\Parallel\JobOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\StreamOutput;

class JobOutputTest extends TestCase
{
    /** @var list<string> */
    private array $lines = [];

    public function testEachCompleteLineIsReported(): void
    {
        $output = $this->createOutput();

        $output->write("first\nsec");
        $output->writeln('ond');
        $output->write('third');

        $this->assertSame(['first', 'second'], $this->lines);
        $this->assertSame('third', $output->getPendingLine());
    }

    public function testTheLastLineIsReportedOnFlush(): void
    {
        $output = $this->createOutput();

        $output->write('last');
        $output->flush();
        $output->flush();

        $this->assertSame(['last'], $this->lines);
        $this->assertSame('', $output->getPendingLine());
    }

    /**
     * A process can send the "\r" of a "\r\n" in a chunk, and the "\n" in the
     * next one.
     */
    public function testACrlfSplitBetweenTwoWritesEndsTheLine(): void
    {
        $output = $this->createOutput();

        $output->write("windows\r");
        $output->write("\nline\r\n");

        $this->assertSame(['windows', 'line'], $this->lines);
    }

    public function testOnlyTheLastStateOfARedrawnLineIsKept(): void
    {
        $output = $this->createOutput();

        $output->write("10%\r");
        $output->write("50%\r");
        $this->assertSame('50%', $output->getPendingLine());

        $output->write("100%\ndone\n");
        $this->assertSame(['100%', 'done'], $this->lines);
    }

    public function testTheProgressBarsOfSymfonyConsoleAreFollowed(): void
    {
        $output = $this->createOutput(decorated: true);
        $progressBar = new ProgressBar($output, 3, 0);
        $progressBar->setFormat('%current%/%max%');

        $progressBar->start();
        $progressBar->advance();
        $this->assertSame('1/3', $output->getPendingLine());

        $progressBar->advance();
        $this->assertSame('2/3', $output->getPendingLine());
        $this->assertSame([], $this->lines);
    }

    public function testTheCursorMovesOfMultilineProgressBarsAreDropped(): void
    {
        $output = $this->createOutput();

        $output->write("step 1\n\e[1A\e[1G\e[2Kstep 2\n");

        $this->assertSame(['step 1', 'step 2'], $this->lines);
    }

    public function testAHugeLineIsKept(): void
    {
        $output = $this->createOutput();

        $output->writeln(str_repeat('x', 4_000_000));

        $this->assertSame([str_repeat('x', 4_000_000)], $this->lines);
    }

    public function testTheFormattingOfTheParentOutputIsUsed(): void
    {
        $parentOutput = new StreamOutput(fopen('php://memory', 'w+'), StreamOutput::VERBOSITY_VERBOSE, true);
        $output = new JobOutput($parentOutput, $this->addLine(...));

        $output->writeln('<info>colored</info>');

        $this->assertTrue($output->isVerbose());
        $this->assertSame(["\e[32mcolored\e[39m"], $this->lines);
    }

    private function createOutput(bool $decorated = false): JobOutput
    {
        $parentOutput = new BufferedOutput();
        $output = new JobOutput($parentOutput, $this->addLine(...));
        $output->setDecorated($decorated);

        return $output;
    }

    private function addLine(string $line): void
    {
        $this->lines[] = $line;
    }
}
