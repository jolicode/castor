<?php

namespace Castor\Runner\Job;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * A named part of a task, with the output it produces: the context of a
 * command or of with() names its job, and each named function given to
 * parallel() is one.
 *
 * A job runs while something runs in it (a command, the callback of with(),
 * a function given to parallel()), and can run again later: its duration is
 * the total of the time it ran.
 *
 * @internal
 */
final class Job
{
    public readonly JobOutput $errorOutput;
    /** The last error that ended a run of the job */
    public private(set) ?\Throwable $exception = null;
    public readonly JobOutput $output;

    /** How many things run in the job */
    private int $activities = 0;
    /** The time the job ran, before its current run */
    private float $elapsed = 0.0;
    private ?float $runningSince = null;

    /**
     * @param \Closure(self, string): void $onLine      Called for each complete line of the output
     * @param \Closure(self, string): void $onErrorLine Called for each complete line of the error output
     */
    public function __construct(
        public readonly string $name,
        OutputInterface $parentOutput,
        OutputInterface $parentErrorOutput,
        \Closure $onLine,
        \Closure $onErrorLine,
    ) {
        $this->output = new JobOutput($parentOutput, fn (string $line) => $onLine($this, $line));
        $this->errorOutput = new JobOutput($parentErrorOutput, fn (string $line) => $onErrorLine($this, $line));
    }

    /**
     * @return bool Whether the job starts running
     */
    public function begin(): bool
    {
        if (0 !== $this->activities++) {
            return false;
        }

        $this->runningSince = microtime(true);

        return true;
    }

    /**
     * @return bool Whether the job stops running
     */
    public function end(?\Throwable $exception = null): bool
    {
        if (0 === $this->activities) {
            throw new \LogicException(\sprintf('The job "%s" is not running.', $this->name));
        }

        if (1 === $this->activities) {
            // Ends the line being written while the job still runs, so it
            // is shown with what the job printed
            $this->output->flush();
            $this->errorOutput->flush();
        }

        if ($exception) {
            $this->exception = $exception;
        }

        if (0 !== --$this->activities) {
            return false;
        }

        $this->elapsed += microtime(true) - $this->runningSince;
        $this->runningSince = null;

        return true;
    }

    public function isRunning(): bool
    {
        return 0 !== $this->activities;
    }

    public function hasFailed(): bool
    {
        return null !== $this->exception;
    }

    public function isSuccessful(): bool
    {
        return !$this->isRunning() && !$this->hasFailed();
    }

    public function getDuration(): float
    {
        return $this->elapsed + (null === $this->runningSince ? 0.0 : microtime(true) - $this->runningSince);
    }
}
