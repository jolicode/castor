<?php

namespace Castor\Helper;

use Castor\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
final class ParallelHelper
{
    /**
     * @return array<mixed>
     */
    public static function parallel(Application $app, OutputInterface $output, callable ...$callbacks): array
    {
        /** @var \Fiber[] $fibers */
        $fibers = [];
        $exceptions = [];

        foreach ($callbacks as $callback) {
            $fiber = new \Fiber($callback);

            try {
                $fiber->start();
            } catch (\Throwable $e) {
                if ($output instanceof ConsoleOutput) {
                    $output = $output->getErrorOutput();
                }

                $app->renderThrowable($e, $output);

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
                        if ($output instanceof ConsoleOutput) {
                            $output = $output->getErrorOutput();
                        }

                        $app->renderThrowable($e, $output);

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

        return array_map(fn ($fiber) => $fiber->getReturn(), $fibers);
    }
}
