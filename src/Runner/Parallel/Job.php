<?php

namespace Castor\Runner\Parallel;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * A named callback run by parallel(), with the output it produces.
 *
 * @internal
 */
final class Job
{
    public readonly JobOutput $errorOutput;
    public private(set) ?\Throwable $exception = null;
    public readonly JobOutput $output;

    private ?float $finishedAt = null;
    private readonly float $startedAt;

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
        $this->startedAt = microtime(true);
    }

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

    public function isSuccessful(): bool
    {
        return $this->isFinished() && null === $this->exception;
    }

    public function getDuration(): float
    {
        return ($this->finishedAt ?? microtime(true)) - $this->startedAt;
    }
}
