<?php

namespace Castor\Runner\Parallel;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * A named function run by parallel(), or an item keyed by a name run by
 * parallel_map(): the job() function returns the one the code is run in.
 */
final class Job
{
    /** @internal */
    public readonly JobOutput $errorOutput;
    public private(set) ?\Throwable $exception = null;

    /** @internal */
    public readonly JobOutput $output;
    public private(set) string $status = '';

    private ?float $finishedAt = null;
    private bool $markedAsFailed = false;
    private bool $markedAsSkipped = false;
    private ?float $startedAt = null;

    /**
     * @internal
     *
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
     * Sets the text shown next to the name of the job, like what it is
     * doing, then what it did once it is finished.
     */
    public function setStatus(string $status): void
    {
        // Shown on a single line
        $this->status = trim(strtr($status, "\r\n", '  '));
    }

    /**
     * Shows the job as failed once it is finished, without throwing: its
     * function still returns its result.
     */
    public function markAsFailed(): void
    {
        $this->markedAsFailed = true;
    }

    /**
     * Shows the job as skipped once it is finished, unless it fails.
     */
    public function markAsSkipped(): void
    {
        $this->markedAsSkipped = true;
    }

    /**
     * @internal
     */
    public function start(): void
    {
        $this->startedAt = microtime(true);
    }

    public function isStarted(): bool
    {
        return null !== $this->startedAt;
    }

    /**
     * @internal
     */
    public function finish(?\Throwable $exception = null): void
    {
        $this->output->flush();
        $this->errorOutput->flush();
        $this->finishedAt = microtime(true);
        $this->exception = $exception;
    }

    public function isFinished(): bool
    {
        return null !== $this->finishedAt;
    }

    public function isFailed(): bool
    {
        return $this->isFinished() && ($this->markedAsFailed || null !== $this->exception);
    }

    public function isSkipped(): bool
    {
        return $this->isFinished() && $this->markedAsSkipped && !$this->isFailed();
    }

    public function isSuccessful(): bool
    {
        return $this->isFinished() && !$this->isFailed() && !$this->isSkipped();
    }

    public function getDuration(): float
    {
        // Waiting for its turn is not part of it
        if (null === $this->startedAt) {
            return 0.0;
        }

        return ($this->finishedAt ?? microtime(true)) - $this->startedAt;
    }
}
