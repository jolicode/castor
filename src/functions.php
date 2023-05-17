<?php

declare(strict_types=1);

namespace Castor;

use Symfony\Component\Process\Process;

function parallel(...$closure): array
{
    $fibers = [];
    foreach ($closure as $item) {
        $fiber = new \Fiber($item);
        $fiber->start();

        $fibers[] = $fiber;
    }

    $isRunning = true;

    while ($isRunning) {
        $isRunning = false;

        foreach ($fibers as $fiber) {
            $isRunning = $isRunning || !$fiber->isTerminated();

            if (!$fiber->isTerminated() && $fiber->isSuspended()) {
                $fiber->resume();
            }
        }

        if (\Fiber::getCurrent()) {
            \Fiber::suspend();
            usleep(1_000);
        }
    }

    return array_map(fn ($fiber) => $fiber->getReturn(), $fibers);
}

function exec(string|array $command, array $parameters = [], ?string $workingDirectory = null,
    array $environment = [],
    array $options = [], bool $tty = false, float|null $timeout = 60): int
{
    global $context;

    if (null === $workingDirectory) {
        $workingDirectory = $context->currentDirectory;
    }

    $environment = array_merge($context->environment, $environment);

    if (\is_array($command)) {
        $process = new Process($command, $workingDirectory, $environment, null, $timeout);
    } else {
        $process = Process::fromShellCommandline($command, $workingDirectory, $environment, null, $timeout);
    }

    if ($tty) {
        $process->setTty(true);
        $process->setInput(\STDIN);
    } else {
        $process->setPty(true);
        $process->setInput(\STDIN);
    }

    $process->start(function ($type, $bytes) {
        if (Process::OUT === $type) {
            fwrite(\STDOUT, $bytes);
        } else {
            fwrite(\STDERR, $bytes);
        }
    });

    if (\Fiber::getCurrent()) {
        while ($process->isRunning()) {
            \Fiber::suspend();
            usleep(1_000);
        }
    }

    return $process->wait();
}

function cd(string $path): void
{
    global $context;

    // if path is absolute
    if (0 === strpos($path, '/')) {
        $context->currentDirectory = $path;
    } else {
        $context->currentDirectory = realpath($context->currentDirectory . '/' . $path);
    }
}
