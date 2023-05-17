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

// Inspired from https://github.com/tartley/rerun2
const WATCH_SCRIPT = <<<'SCRIPT'
inotifywait --recursive --quiet --format '%e %w%f' \
    --event='modify,close_write,move,create,delete' \
    --exclude='\.git|\..*\.swp|\.cache' \
    .
SCRIPT;

function watch(string $path, callable $function): void
{
    if (exec('command -v inotifywait', quiet: true) > 0) {
        fwrite(\STDOUT, 'inotifywait is not installed. You may need to install a package named "inotify-tools".' . PHP_EOL);

        return;
    }

    cd($path);
    fwrite(\STDOUT, sprintf('Waiting for changes in %s...', $path) . PHP_EOL);

    while(true) {
        exec(WATCH_SCRIPT, quiet: true, timeout: null);
        $function();
    }
}
