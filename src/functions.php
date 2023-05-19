<?php

namespace Castor;

use Symfony\Component\Process\Process;

/**
 * @return array<mixed>
 */
function parallel(callable ...$callbacks): array
{
    $fibers = [];
    foreach ($callbacks as $callback) {
        $fiber = new \Fiber($callback);
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

/**
 * @param string|array<string>  $command
 * @param array<string, string> $environment
 */
function exec(
    string|array $command,
    ?string $workingDirectory = null,
    array $environment = [],
    bool $tty = false,
    float|null $timeout = 60,
    bool $quiet = false,
): Process {
    $context = ContextRegistry::$currentContext;

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

    $process->wait();

    return $process;
}

function cd(string $path): void
{
    $context = ContextRegistry::$currentContext;

    // if path is absolute
    if (0 === strpos($path, '/')) {
        $context->currentDirectory = $path;
    } else {
        $context->currentDirectory = PathHelper::realpath($context->currentDirectory . '/' . $path);
    }
}

function watch(string $path, callable $callable, bool $verbose = false): void
{
    $isWindows = '\\' === \DIRECTORY_SEPARATOR;

    if (is_dir($path)) {
        $path = rtrim($path, '/');
        $path .= '/...';
    }

    $process = new Process(
        [__DIR__ . '/../watcher/bin/watcher' . ($isWindows ? '.exe' : ''), $path],
        timeout: null,
    );

    if ($verbose) {
        fwrite(\STDOUT, sprintf('Waiting for changes in "%s".', $path) . \PHP_EOL);
    }

    $process->mustRun(function ($type, $bytes) use ($verbose, $callable) {
        if (Process::ERR === $type) {
            fwrite(\STDERR, $bytes);

            return;
        }

        $lines = explode("\n", trim($bytes));

        foreach ($lines as $line) {
            ['name' => $name, 'operation' => $operation] = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            if ($verbose) {
                fwrite(\STDOUT, sprintf('File "%s", Operation: "%s".', $name, $operation) . \PHP_EOL);
            }

            $callable($name, $operation);
        }
    });
}
