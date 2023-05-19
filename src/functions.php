<?php

namespace Castor;

use Symfony\Component\Process\Process;

function parallel(callable ...$closure): array
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

function process(
    string|array $command,
    ?string $workingDirectory = null,
    array $environment = [],
    bool $tty = false,
    float|null $timeout = 60,
): Process {
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
    } else {
        $process->setPty(true);
    }

    return $process;
}

function exec(
    string|array $command,
    ?string $workingDirectory = null,
    array $environment = [],
    bool $tty = false,
    float|null $timeout = 60,
    bool $quiet = false,
): int {
    $process = process($command, $workingDirectory, $environment, $tty, $timeout);
    $process->setInput(\STDIN);

    $process->start(function ($type, $bytes) use ($quiet) {
        if ($quiet) {
            return;
        }

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

function capture(
    string|array $command,
    ?string $workingDirectory = null,
    array $environment = [],
    bool $tty = false,
    float|null $timeout = 60,
): array {
    $process = process($command, $workingDirectory, $environment, $tty, $timeout);
    $stdout = '';
    $stderr = '';

    $process->start(function ($type, $bytes) use (&$stdout, &$stderr) {
        if (Process::OUT === $type) {
            $stdout .= $bytes;
        } else {
            $stderr .= $bytes;
        }
    });

    if (\Fiber::getCurrent()) {
        while ($process->isRunning()) {
            \Fiber::suspend();
            usleep(1_000);
        }
    }

    $exitCode = $process->wait();

    return [$stdout, $stderr, $exitCode];
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
