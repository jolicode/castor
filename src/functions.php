<?php

namespace Castor;

use Joli\JoliNotif\Notification;
use Joli\JoliNotif\NotifierFactory;
use Symfony\Component\Process\Exception\ProcessFailedException;
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
 * @param string|array<string> $command
 * @param (callable(string, string, Process) :void)|null $callback
 * @param array<string, string>|null $environment
 */
function exec(
    string|array $command,
    bool $quiet = false,
    callable $callback = null,
    bool $allowFailure = false,
    bool $notify = false,
    Context $context = null,
    bool|null $tty = null,
    bool|null $pty = null,
    string $path = null,
    array $environment = null,
): Process {
    $context ??= ContextRegistry::getCurrentContext();

    if ($path) {
        $context = $context->withCd($path);
    }

    if ($environment) {
        $context = $context->withEnvironment($environment);
    }

    if (\is_array($command)) {
        $process = new Process($command, $context->currentDirectory, $context->environment, null, $context->timeout);
    } else {
        $process = Process::fromShellCommandline($command, $context->currentDirectory, $context->environment, null, $context->timeout);
    }

    $tty ??= $context->tty;
    $pty ??= $context->pty;

    if ($tty) {
        $process->setTty(true);
        $process->setInput(\STDIN);
    } elseif ($pty) {
        $process->setPty(true);
        $process->setInput(\STDIN);
    }

    if (!$quiet && !$callback) {
        $callback = static function ($type, $bytes) {
            if (Process::OUT === $type) {
                fwrite(\STDOUT, $bytes);
            } else {
                fwrite(\STDERR, $bytes);
            }
        };
    }

    $process->start(function ($type, $bytes) use ($callback, $process) {
        if ($callback) {
            $callback($type, $bytes, $process);
        }
    });

    if (\Fiber::getCurrent()) {
        while ($process->isRunning()) {
            \Fiber::suspend();
            usleep(1_000);
        }
    }

    $exitCode = $process->wait();

    if ($notify) {
        notify(sprintf('The command "%s" has been finished %s.', $process->getCommandLine(), 0 === $exitCode ? 'successfully' : 'with an error'));
    }

    if (0 !== $exitCode && !$allowFailure) {
        throw new ProcessFailedException($process);
    }

    return $process;
}

function notify(string $message): void
{
    static $notifier;

    $notifier ??= NotifierFactory::create();

    $notification =
        (new Notification())
            ->setTitle('Castor')
            ->setBody($message)
    ;

    $notifier->send($notification);
}

/** @param (callable(string, string) : (false|null)) $function */
function watch(string $path, callable $function, Context $context = null): void
{
    $context ??= ContextRegistry::getCurrentContext();
    $binary = 'watcher';

    if ('\\' === \DIRECTORY_SEPARATOR) {
        $binary = 'watcher.exe';
    }

    $binaryPath = __DIR__ . '/../watcher/bin/' . $binary;

    if (str_starts_with(__FILE__, 'phar:')) {
        static $tmpPath;

        if (null === $tmpPath) {
            $tmpPath = sys_get_temp_dir() . '/' . $binary;
            copy($binaryPath, $tmpPath);
            chmod($tmpPath, 0o755);
        }

        $binaryPath = $tmpPath;
    }

    $watchContext = $context->withTty(false)->withPty(false)->withTimeout(null);

    $command = [$binaryPath, $path];
    $buffer = '';

    exec($command, callback: static function ($type, $bytes, $process) use ($function, &$buffer) {
        if (Process::OUT === $type) {
            $data = $buffer . $bytes;
            $lines = explode("\n", $data);

            while (!empty($lines)) {
                $line = trim($lines[0]);

                if ('' === $line) {
                    array_shift($lines);

                    continue;
                }

                try {
                    $eventLine = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $buffer = implode("\n", $lines);

                    break;
                }

                $result = $function($eventLine['name'], $eventLine['operation']);

                if (false === $result) {
                    $process->stop();
                }

                array_shift($lines);
            }
        } else {
            fwrite(\STDERR, "ERROR: {$type} : " . $bytes);
        }
    }, context: $watchContext);
}

/**
 * @param array<string, mixed> $context
 */
function log(string $message, string $level = 'info', array $context = []): void
{
    ContextRegistry::getLogger()->log($level, $message, $context);
}
