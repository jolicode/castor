<?php

namespace Castor;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

// Disabled for now, see https://github.com/jolicode/castor/pull/251

/** @internal */
class SectionOutput
{
    private const COLORS = ['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white', 'default'];

    private OutputInterface|ConsoleSectionOutput $consoleOutput;

    private ConsoleOutput|null $mainOutput;

    /** @var \SplObjectStorage<Process, SectionDetails> */
    private \SplObjectStorage $sections;

    public function __construct(OutputInterface $output)
    {
        $this->consoleOutput = $output;
        $this->mainOutput = null;
        $this->sections = new \SplObjectStorage();

        if ($output instanceof ConsoleOutput && posix_isatty(\STDOUT) && 'true' === getenv('CASTOR_USE_SECTION')) {
            $this->mainOutput = $output;
            $this->consoleOutput = $output->section();
        }
    }

    public function getConsoleOutput(): OutputInterface
    {
        return $this->consoleOutput;
    }

    public function writeProcessOutput(string $type, string $bytes, Process $process): void
    {
        if (!$this->mainOutput) {
            if (Process::OUT === $type) {
                fwrite(\STDOUT, $bytes);
            } else {
                fwrite(\STDERR, $bytes);
            }

            return;
        }

        $this->getSectionDetails($process)->section->write($bytes);

        $this->tickProcess($process);
    }

    public function initProcess(Process $process): void
    {
        if (!$this->mainOutput) {
            return;
        }

        $this->getSectionDetails($process);
    }

    public function finishProcess(Process $process): void
    {
        if (!$this->mainOutput) {
            return;
        }

        $sectionDetails = $this->getSectionDetails($process);
        $outputContent = $sectionDetails->section->getContent();
        $time = number_format(microtime(true) - $sectionDetails->start, 2);

        $fg = 0 === $process->getExitCode() ? 'green' : 'red';
        $status = 0 === $process->getExitCode() ? 'success' : 'failure';

        $color = self::COLORS[$sectionDetails->index % \count(self::COLORS)];

        $this->consoleOutput->writeln("<bg={$color}> </>[{$sectionDetails->index}] <fg={$fg}>{$process->getCommandLine()}</> {$status} after {$time}s");
        $this->consoleOutput->write($outputContent);

        $sectionDetails->section->clear();
        $sectionDetails->progressBarSection->clear();
    }

    public function tickProcess(Process $process): void
    {
        if (!$this->mainOutput) {
            return;
        }

        $sectionDetails = $this->getSectionDetails($process);
        $time = number_format(microtime(true) - $sectionDetails->start, 2);
        $color = self::COLORS[$sectionDetails->index % \count(self::COLORS)];

        $sectionDetails->progressBarSection->writeln("<bg={$color}> </>[{$sectionDetails->index}] <fg=yellow>{$process->getCommandLine()}</> running for {$time}s");
    }

    private function getSectionDetails(Process $process): SectionDetails
    {
        if (!$this->mainOutput) {
            throw new \LogicException('Cannot call getSectionDetails() without a main output.');
        }

        if (!$this->sections->contains($process) || ('' === $this->sections[$process]->progressBarSection->getContent())) {
            $progressBarSection = $this->mainOutput->section();
            $section = $this->mainOutput->section();
            $index = sprintf('%02d', \count($this->sections) + 1);
            $color = self::COLORS[$index % \count(self::COLORS)];
            $progressBarSection->writeln("<bg={$color}> </>[{$index}] <fg=yellow>{$process->getCommandLine()}</> starting...");
            $progressBarSection->setDecorated(true);
            $progressBarSection->setMaxHeight(1);

            $this->sections[$process] = new SectionDetails($section, $progressBarSection, microtime(true), $index);
        }

        return $this->sections[$process];
    }
}

class SectionDetails
{
    public function __construct(
        public ConsoleSectionOutput $section,
        public ConsoleSectionOutput $progressBarSection,
        public float $start,
        public string $index,
    ) {
    }
}
