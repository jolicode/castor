<?php

namespace Castor\Runner;

use Castor\Console\Application;
use Castor\ContextRegistry;
use Castor\Runner\Job\Job;
use Castor\Runner\Job\JobRegistry;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
final readonly class ParallelRunner
{
    public function __construct(
        private Application $app,
        private OutputInterface $output,
        private ContextRegistry $contextRegistry,
        private JobRegistry $jobRegistry,
    ) {
    }

    /**
     * @return array<array-key, mixed>
     */
    public function parallel(callable ...$callbacks): array
    {
        /** @var array<\Fiber<mixed, mixed, mixed, mixed>> $fibers */
        $fibers = [];
        $exceptions = [];
        $errorOutput = $this->output;
        if ($errorOutput instanceof ConsoleOutputInterface) {
            $errorOutput = $errorOutput->getErrorOutput();
        }

        // The callbacks inherit the job they are run from, if any, so their
        // output still lands in this job
        $parentJob = $this->jobRegistry->getCurrent();
        // Named callbacks are jobs of their own, and their output is organized
        $isNamed = $callbacks && !array_is_list($callbacks);
        if ($isNamed) {
            if (array_filter(array_keys($callbacks), is_int(...))) {
                throw new \InvalidArgumentException('The functions given to parallel() must be either all named or all unnamed.');
            }

            // All at once, to align their names
            foreach (array_keys($callbacks) as $name) {
                $this->jobRegistry->get((string) $name);
            }
        }

        // Each fiber gets its own "current context" slot, since Fiber::start() /
        // Fiber::resume() run interleaved with the other fibers and with this method
        // itself, and ContextRegistry only tracks a single global current context.
        // Without this, with() inside a fiber would save/restore the wrong context
        // whenever another fiber runs (starts, resumes, or calls with()) while it is
        // suspended.
        $outerContext = $this->contextRegistry->hasCurrentContext()
            ? $this->contextRegistry->getCurrentContext()
            : null;

        /** @var \SplObjectStorage<\Fiber<mixed, mixed, mixed, mixed>, \Castor\Context> $fiberContexts */
        $fiberContexts = new \SplObjectStorage();
        /** @var \SplObjectStorage<\Fiber<mixed, mixed, mixed, mixed>, Job> $fiberJobs The jobs of the named callbacks */
        $fiberJobs = new \SplObjectStorage();

        $handleThrowable = function (\Throwable $e, \Fiber $fiber) use ($errorOutput, $fiberJobs, $parentJob, &$exceptions): void {
            $exceptions[] = $e;

            if (!isset($fiberJobs[$fiber])) {
                if (!$parentJob) {
                    $this->jobRegistry->suspend();
                }
                $this->app->renderThrowable($e, $parentJob->errorOutput ?? $errorOutput);

                return;
            }

            $job = $fiberJobs[$fiber];
            // Ended first, so what it was writing comes before its error
            $this->jobRegistry->end($job, $e);
            // Separated from the output of the job, which renderThrowable()
            // does not know about
            $job->errorOutput->writeln('', OutputInterface::VERBOSITY_QUIET);
            $this->app->renderThrowable($e, $job->errorOutput);
        };
        $handleTermination = function (\Fiber $fiber) use ($fiberJobs): void {
            if (isset($fiberJobs[$fiber]) && $fiber->isTerminated()) {
                $this->jobRegistry->end($fiberJobs[$fiber]);
            }
        };

        foreach ($callbacks as $key => $callback) {
            $fiber = new \Fiber($callback);
            $context = $outerContext;

            if ($isNamed) {
                $job = $this->jobRegistry->get((string) $key);
                $fiberJobs[$fiber] = $job;
                $this->jobRegistry->attach($fiber, $job);
                // The job of the context comes first, it must not be the
                // one parallel() is called from
                $context = $context?->withJob((string) $key);
                $this->jobRegistry->begin($job);
            } elseif ($parentJob) {
                $this->jobRegistry->attach($fiber, $parentJob);
            }

            if ($context) {
                $this->contextRegistry->setCurrentContext($context);
            }

            try {
                $fiber->start();
                $handleTermination($fiber);
            } catch (\Throwable $e) {
                $handleThrowable($e, $fiber);
            }

            if ($this->contextRegistry->hasCurrentContext()) {
                $fiberContexts[$fiber] = $this->contextRegistry->getCurrentContext();
            }

            $fibers[$key] = $fiber;
        }

        $isRunning = true;

        while ($isRunning) {
            $isRunning = false;

            foreach ($fibers as $fiber) {
                $isRunning = $isRunning || !$fiber->isTerminated();

                if (!$fiber->isTerminated() && $fiber->isSuspended()) {
                    if (isset($fiberContexts[$fiber])) {
                        $this->contextRegistry->setCurrentContext($fiberContexts[$fiber]);
                    }

                    try {
                        $fiber->resume();
                        $handleTermination($fiber);
                    } catch (\Throwable $e) {
                        $handleThrowable($e, $fiber);
                    }

                    if ($this->contextRegistry->hasCurrentContext()) {
                        $fiberContexts[$fiber] = $this->contextRegistry->getCurrentContext();
                    }
                }
            }

            $this->jobRegistry->tick();

            if (!$isRunning) {
                break;
            }

            // A single pause per round, whatever the number of fibers: a
            // parallel() nested in a fiber leaves it to the outermost one.
            if (\Fiber::getCurrent()) {
                \Fiber::suspend();
            } else {
                usleep(20_000);
            }
        }

        if ($outerContext) {
            $this->contextRegistry->setCurrentContext($outerContext);
        }

        // Done with its jobs: what comes next is shown below them
        if ($isNamed && !$parentJob && null === \Fiber::getCurrent()) {
            $this->jobRegistry->suspend();
        }

        if ($exceptions) {
            throw new \RuntimeException('One or more exceptions were thrown in parallel.');
        }

        return array_map(static fn ($fiber): mixed => $fiber->getReturn(), $fibers);
    }
}
