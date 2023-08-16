<?php

namespace Castor;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/** @internal */
class SectionOutput
{
    private OutputInterface|ConsoleSectionOutput $consoleOutput;

    private ConsoleOutput|null $mainOutput;

    /** @var array{ConsoleSectionOutput, ConsoleSectionOutput, float, string}[] */
    private array $sections = [];

    public function __construct(OutputInterface $output)
    {
        $this->mainOutput = null;
        $this->consoleOutput = $output;

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

        /** @var ConsoleSectionOutput $section */
        [$section] = $this->getSection($process);
        $section->write($bytes);
        $this->tickProcess($process);
    }

    public function initProcess(Process $process): void
    {
        if (!$this->mainOutput) {
            return;
        }

        $this->getSection($process);
    }

    public function finishProcess(Process $process): void
    {
        if (!$this->mainOutput) {
            return;
        }

        [$outputSection, $progressBarSection, $start, $index] = $this->getSection($process);
        $outputContent = $outputSection->getContent();
        $time = number_format(microtime(true) - $start, 2);

        $fg = 0 === $process->getExitCode() ? 'green' : 'red';
        $status = 0 === $process->getExitCode() ? 'success' : 'failure';

        $this->consoleOutput->writeln("[RUN] [{$index}] <fg={$fg}>{$process->getCommandLine()}</> {$status} after {$time}s");
        $this->consoleOutput->write($outputContent);

        $outputSection->clear();
        $progressBarSection->clear();
    }

    public function tickProcess(Process $process): void
    {
        if (!$this->mainOutput) {
            return;
        }

        /* @var $progressBar ProgressBar */
        [, $progressBarSection, $start, $index] = $this->getSection($process);
        $time = number_format(microtime(true) - $start, 2);
        $progressBarSection->writeln("[RUN] [{$index}] '<fg=yellow>{$process->getCommandLine()}</>' running for {$time}s");
    }

    /**
     * @return array{ConsoleSectionOutput, ConsoleSectionOutput, float, string}
     */
    private function getSection(Process $process): array
    {
        if (!$this->mainOutput) {
            throw new \LogicException('Cannot call getSection() without a main output.');
        }

        $id = spl_object_hash($process);

        if (!isset($this->sections[$id]) || ('' === $this->sections[$id][1]->getContent())) {
            $progressBarSection = $this->mainOutput->section();
            $section = $this->mainOutput->section();
            $index = sprintf('%02d', \count($this->sections) + 1);
            $progressBarSection->writeln("[RUN] [{$index}] '<fg=yellow>{$process->getCommandLine()}</>' starting...");
            $progressBarSection->setDecorated(true);
            $progressBarSection->setMaxHeight(1);

            $this->sections[$id] = [$section, $progressBarSection, microtime(true), $index];
        }

        return $this->sections[$id];
    }
}
