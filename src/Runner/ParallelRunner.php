<?php

namespace Castor\Runner;

use Castor\Console\Application;
use Castor\ContextRegistry;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
final readonly class ParallelRunner
{
    public function __construct(
        private Application $app,
        private OutputInterface $output,
        private ContextRegistry $contextRegistry,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function parallel(callable ...$callbacks): array
    {
        /** @var \Fiber[] $fibers */
        $fibers = [];
        $exceptions = [];
        $errorOutput = $this->output;
        if ($errorOutput instanceof ConsoleOutput) {
            $errorOutput = $errorOutput->getErrorOutput();
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

        /** @var \SplObjectStorage<\Fiber, \Castor\Context> $fiberContexts */
        $fiberContexts = new \SplObjectStorage();

        foreach ($callbacks as $callback) {
            $fiber = new \Fiber($callback);

            if ($outerContext) {
                $this->contextRegistry->setCurrentContext($outerContext);
            }

            try {
                $fiber->start();
            } catch (\Throwable $e) {
                $this->app->renderThrowable($e, $errorOutput);

                $exceptions[] = $e;
            }

            if ($this->contextRegistry->hasCurrentContext()) {
                $fiberContexts[$fiber] = $this->contextRegistry->getCurrentContext();
            }

            $fibers[] = $fiber;
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
                    } catch (\Throwable $e) {
                        $this->app->renderThrowable($e, $errorOutput);

                        $exceptions[] = $e;
                    }

                    if ($this->contextRegistry->hasCurrentContext()) {
                        $fiberContexts[$fiber] = $this->contextRegistry->getCurrentContext();
                    }
                }
            }

            if (\Fiber::getCurrent()) {
                \Fiber::suspend();
                usleep(1_000);
            }
        }

        if ($outerContext) {
            $this->contextRegistry->setCurrentContext($outerContext);
        }

        if ($exceptions) {
            throw new \RuntimeException('One or more exceptions were thrown in parallel.');
        }

        return array_map(static fn ($fiber): mixed => $fiber->getReturn(), $fibers);
    }
}
