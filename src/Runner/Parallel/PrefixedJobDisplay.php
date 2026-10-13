<?php

namespace Castor\Runner\Parallel;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints each line as soon as it comes, prefixed with the name of its job,
 * and a summary of the jobs when one of them failed.
 *
 * Used when the output is not an interactive terminal (CI, pipe, file…), in
 * verbose or quiet mode, and when another display may already use the
 * terminal.
 *
 * @internal
 */
final class PrefixedJobDisplay implements JobDisplayInterface
{
    private const COLORS = ['cyan', 'magenta', 'yellow', 'blue', 'green', 'red'];

    /** @var array<string, Job> */
    private array $jobs = [];
    /** @var array<string, string> */
    private array $prefixes = [];

    /**
     * @param non-empty-list<string> $names
     */
    public function __construct(
        private readonly OutputInterface $output,
        private readonly OutputInterface $errorOutput,
        array $names,
    ) {
        $nameColumn = new JobNameColumn($names);

        foreach ($names as $i => $name) {
            $color = self::COLORS[$i % \count(self::COLORS)];
            $this->prefixes[$name] = \sprintf('<fg=%s>%s</> ', $color, OutputFormatter::escape($nameColumn->format($name, '[', ']')));
            $this->jobs[$name] = new Job($name, $output, $errorOutput, $this->writeLine(...), $this->writeErrorLine(...));
        }
    }

    public function getJob(string $name): Job
    {
        return $this->jobs[$name];
    }

    public function tick(): void
    {
    }

    public function finish(Job $job): void
    {
        $this->output->writeln($this->prefixes[$job->name] . $this->formatStatus($job));
    }

    public function stop(): void
    {
        if (!array_any($this->jobs, static fn (Job $job) => !$job->isSuccessful())) {
            return;
        }

        // The failures are lost in the middle of the output of the other jobs
        $this->output->writeln('');
        foreach ($this->jobs as $job) {
            $this->output->writeln($this->prefixes[$job->name] . $this->formatStatus($job));
        }
    }

    private function formatStatus(Job $job): string
    {
        $duration = number_format($job->getDuration(), 1);

        return $job->isSuccessful()
            ? "<fg=green>✔ Done in {$duration}s</>"
            : "<fg=red>✘ Failed after {$duration}s</>";
    }

    private function writeLine(Job $job, string $line): void
    {
        $this->writePrefixedLine($this->output, $job, $line);
    }

    private function writeErrorLine(Job $job, string $line): void
    {
        $this->writePrefixedLine($this->errorOutput, $job, $line);
    }

    private function writePrefixedLine(OutputInterface $output, Job $job, string $line): void
    {
        // The blocks of io() are padded to the width of the terminal, which
        // the prefix would exceed
        $line = rtrim($line);
        if ('' === $line) {
            return;
        }

        // The output of the job already filtered the line on its verbosity
        $output->writeln($this->prefixes[$job->name] . OutputFormatter::escape($line), OutputInterface::VERBOSITY_QUIET);
    }
}
