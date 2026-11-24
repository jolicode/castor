<?php

namespace Castor\Runner;

use Castor\Console\Application;
use Castor\ContextRegistry;
use Castor\Exception\ParallelException;
use Castor\Runner\Parallel\Job;
use Castor\Runner\Parallel\JobDisplayInterface;
use Castor\Runner\Parallel\JobRegistry;
use Castor\Runner\Parallel\PrefixedJobDisplay;
use Castor\Runner\Parallel\TuiJobDisplay;
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
        return $this->run($callbacks, null, 'The functions given to parallel() must be either all named or all unnamed.');
    }

    /**
     * @template TKey of array-key
     * @template TItem
     * @template TResult
     *
     * @param array<TKey, TItem>       $items
     * @param callable(TItem): TResult $callback
     *
     * @return array<TKey, TResult>
     */
    public function map(array $items, callable $callback, ?int $concurrency = null): array
    {
        if (null !== $concurrency && $concurrency < 1) {
            throw new \InvalidArgumentException('The concurrency given to parallel_map() must be at least 1.');
        }

        /** @var array<TKey, TResult> $results */
        $results = $this->run(
            array_map(static fn (mixed $item) => static fn () => $callback($item), $items),
            $concurrency,
            'The items given to parallel_map() must be either all keyed by a name or all keyed by an integer.',
        );

        return $results;
    }

    /**
     * @param array<array-key, callable> $callbacks
     * @param ?int                       $concurrency How many callbacks run at the same time, all of them when null
     *
     * @return array<array-key, mixed>
     */
    private function run(array $callbacks, ?int $concurrency, string $mixedKeysMessage): array
    {
        /** @var array<\Fiber<mixed, mixed, mixed, mixed>> $fibers */
        $fibers = [];
        /** @var array<array-key, \Throwable> $exceptions */
        $exceptions = [];
        $errorOutput = $this->output;
        if ($errorOutput instanceof ConsoleOutputInterface) {
            $errorOutput = $errorOutput->getErrorOutput();
        }

        // The callbacks inherit the job they are run from, if any, so their
        // output still lands in this job
        $parentJob = $this->jobRegistry->getCurrent();
        // Named callbacks get their own job, and their output is organized
        $display = null;
        if ($names = array_values(array_filter(array_keys($callbacks), is_string(...)))) {
            if (\count($names) !== \count($callbacks)) {
                throw new \InvalidArgumentException($mixedKeysMessage);
            }

            $display = $this->createDisplay($names, $parentJob, $errorOutput);
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
        /** @var \SplObjectStorage<\Fiber<mixed, mixed, mixed, mixed>, Job> $fiberJobs */
        $fiberJobs = new \SplObjectStorage();

        $handleThrowable = function (\Throwable $e, \Fiber $fiber, int|string $key) use ($display, $errorOutput, $fiberJobs, $parentJob, &$exceptions): void {
            $exceptions[$key] = $e;

            if (!$display) {
                $this->app->renderThrowable($e, $parentJob->errorOutput ?? $errorOutput);

                return;
            }

            // Finished first, so what it was writing comes before its error
            $fiberJobs[$fiber]->finish($e);
            // Separated from the output of the job, which renderThrowable()
            // does not know about
            $fiberJobs[$fiber]->errorOutput->writeln('', OutputInterface::VERBOSITY_QUIET);
            $this->app->renderThrowable($e, $fiberJobs[$fiber]->errorOutput);
            $display->finish($fiberJobs[$fiber]);
        };
        $handleTermination = static function (\Fiber $fiber) use ($display, $fiberJobs): void {
            if ($display && $fiber->isTerminated()) {
                $fiberJobs[$fiber]->finish();
                $display->finish($fiberJobs[$fiber]);
            }
        };

        // Without it, echo, print or var_dump() in a job would write straight
        // to the console. Only when nothing else runs: the output buffers are
        // a stack, which fibers would not pop in order.
        $capturesOutput = $display && null === \Fiber::getCurrent();
        if ($capturesOutput) {
            ob_start($this->writeToCurrentJob(...), 1);
        }

        $pending = $callbacks;

        try {
            while (true) {
                $running = 0;

                foreach ($fibers as $key => $fiber) {
                    if ($fiber->isTerminated()) {
                        continue;
                    }

                    if ($fiber->isSuspended()) {
                        if (isset($fiberContexts[$fiber])) {
                            $this->contextRegistry->setCurrentContext($fiberContexts[$fiber]);
                        }

                        try {
                            $fiber->resume();
                            $handleTermination($fiber);
                        } catch (\Throwable $e) {
                            $handleThrowable($e, $fiber, $key);
                        }

                        if ($this->contextRegistry->hasCurrentContext()) {
                            $fiberContexts[$fiber] = $this->contextRegistry->getCurrentContext();
                        }
                    }

                    if (!$fiber->isTerminated()) {
                        ++$running;
                    }
                }

                // Started in the order they were given, as soon as there is
                // room for them
                while ($pending && (null === $concurrency || $running < $concurrency)) {
                    $key = array_key_first($pending);
                    $fiber = new \Fiber($pending[$key]);
                    unset($pending[$key]);

                    if ($display) {
                        $job = $display->getJob((string) $key);
                        $job->start();
                    } else {
                        $job = $parentJob;
                    }

                    if ($job) {
                        $this->jobRegistry->attach($fiber, $job);
                        $fiberJobs[$fiber] = $job;
                    }

                    if ($outerContext) {
                        $this->contextRegistry->setCurrentContext($outerContext);
                    }

                    try {
                        $fiber->start();
                        $handleTermination($fiber);
                    } catch (\Throwable $e) {
                        $handleThrowable($e, $fiber, $key);
                    }

                    if ($this->contextRegistry->hasCurrentContext()) {
                        $fiberContexts[$fiber] = $this->contextRegistry->getCurrentContext();
                    }

                    $fibers[$key] = $fiber;

                    if (!$fiber->isTerminated()) {
                        ++$running;
                    }
                }

                $display?->tick();

                if (!$pending && 0 === $running) {
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
        } finally {
            if ($capturesOutput) {
                ob_end_flush();
            }

            $display?->stop();
        }

        if ($outerContext) {
            $this->contextRegistry->setCurrentContext($outerContext);
        }

        $getReturn = static fn (\Fiber $fiber): mixed => $fiber->getReturn();

        if ($exceptions) {
            throw new ParallelException($exceptions, array_map($getReturn, array_diff_key($fibers, $exceptions)));
        }

        return array_map($getReturn, $fibers);
    }

    /**
     * @param non-empty-list<string> $names
     */
    private function createDisplay(array $names, ?Job $parentJob, OutputInterface $errorOutput): JobDisplayInterface
    {
        // Nested in another job: everything goes in the output of this job
        if ($parentJob) {
            return new PrefixedJobDisplay($parentJob->output, $parentJob->errorOutput, $names);
        }

        // In a fiber that is not a job (unnamed parallel(), watch()), the code
        // running next to this one writes to the console too: only prefixed
        // lines can mix with it
        if (null === \Fiber::getCurrent() && TuiJobDisplay::isSupported($this->output, \count($names))) {
            return new TuiJobDisplay($this->output, $errorOutput, $names);
        }

        return new PrefixedJobDisplay($this->output, $errorOutput, $names);
    }

    private function writeToCurrentJob(string $buffer): string
    {
        if (!$job = $this->jobRegistry->getCurrent()) {
            return $buffer;
        }

        // Shown even in quiet mode, like when it is not in a job
        $job->output->write($buffer, false, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);

        return '';
    }
}
