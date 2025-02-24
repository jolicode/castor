<?php

namespace Castor\Runner;

use Castor\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
final readonly class ParallelRunner
{
    public function __construct(
        private Application $app,
        private OutputInterface $output,
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

        foreach ($callbacks as $callback) {
            $fiber = new \Fiber($callback);

            try {
                $fiber->start();
            } catch (\Throwable $e) {
                $this->app->renderThrowable($e, $errorOutput);

                $exceptions[] = $e;
            }

            $fibers[] = $fiber;
        }

        $isRunning = true;

        while ($isRunning) {
            $isRunning = false;

            foreach ($fibers as $fiber) {
                $isRunning = $isRunning || !$fiber->isTerminated();

                if (!$fiber->isTerminated() && $fiber->isSuspended()) {
                    try {
                        $fiber->resume();
                    } catch (\Throwable $e) {
                        $this->app->renderThrowable($e, $errorOutput);

                        $exceptions[] = $e;
                    }
                }
            }

            if (\Fiber::getCurrent()) {
                \Fiber::suspend();
                usleep(1_000);
            }
        }

        if ($exceptions) {
            throw new \RuntimeException('One or more exceptions were thrown in parallel.');
        }

        return array_map(fn ($fiber): mixed => $fiber->getReturn(), $fibers);
    }
}
