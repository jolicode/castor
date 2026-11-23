<?php

namespace Castor\Runner\Job;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints each line as soon as it comes, prefixed with the name of its job,
 * and a summary of the jobs when one of them failed.
 *
 * Used when the output is not an interactive terminal (CI, pipe, file…), and
 * in verbose or quiet mode.
 *
 * @internal
 */
final class PrefixedJobDisplay implements JobDisplayInterface
{
    private const COLORS = ['cyan', 'magenta', 'yellow', 'blue', 'green', 'red'];

    /** @var array<string, string> */
    private array $colors = [];
    private readonly JobNameColumn $nameColumn;
    /** @var array<string, Job> */
    private array $jobs = [];

    public function __construct(
        private readonly OutputInterface $output,
        private readonly OutputInterface $errorOutput,
    ) {
        $this->nameColumn = new JobNameColumn();
    }

    public function add(Job $job): void
    {
        $this->colors[$job->name] = self::COLORS[\count($this->jobs) % \count(self::COLORS)];
        $this->jobs[$job->name] = $job;
        $this->nameColumn->add($job->name);
    }

    public function addLine(Job $job, string $line, bool $isError): void
    {
        // The blocks of io() are padded to the width of the terminal, which
        // the prefix would exceed
        $line = rtrim($line);
        if ('' === $line) {
            return;
        }

        // The output of the job already filtered the line on its verbosity
        ($isError ? $this->errorOutput : $this->output)->writeln($this->getPrefix($job) . OutputFormatter::escape($line), OutputInterface::VERBOSITY_QUIET);
    }

    public function update(Job $job): void
    {
        if (!$job->isRunning()) {
            $this->output->writeln($this->getPrefix($job) . $this->formatStatus($job));
        }
    }

    public function tick(): void
    {
    }

    public function suspend(): void
    {
    }

    public function stop(): void
    {
        if (!array_any($this->jobs, static fn (Job $job) => $job->hasFailed())) {
            return;
        }

        // The failures are lost in the middle of the output of the other jobs
        $this->output->writeln('');
        foreach ($this->jobs as $job) {
            $this->output->writeln($this->getPrefix($job) . $this->formatStatus($job));
        }
    }

    private function getPrefix(Job $job): string
    {
        return \sprintf('<fg=%s>%s</> ', $this->colors[$job->name], OutputFormatter::escape($this->nameColumn->format($job->name, '[', ']')));
    }

    private function formatStatus(Job $job): string
    {
        $duration = number_format($job->getDuration(), 1);

        return match (true) {
            $job->hasFailed() => "<fg=red>✘ Failed after {$duration}s</>",
            $job->isRunning() => "<fg=yellow>Running for {$duration}s</>",
            default => "<fg=green>✔ Done in {$duration}s</>",
        };
    }
}
