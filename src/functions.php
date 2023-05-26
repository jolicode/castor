<?php

namespace Castor;

use Joli\JoliNotif\Notification;
use Joli\JoliNotif\NotifierFactory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
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
 * @param string|array<string>                           $command
 * @param (callable(string, string, Process) :void)|null $callback
 * @param array<string, string>|null                     $environment
 */
function exec(
    string|array $command,
    array|null $environment = null,
    string|null $path = null,
    bool|null $tty = null,
    bool|null $pty = null,
    float|null $timeout = null,
    bool|null $quiet = null,
    bool|null $allowFailure = null,
    bool|null $notify = null,
    callable $callback = null,
    Context $context = null,
): Process {
    $context ??= ContextRegistry::getInitialContext();

    if (null !== $environment) {
        $context = $context->withEnvironment($environment);
    }

    if ($path) {
        $context = $context->withPath($path);
    }

    if (null !== $tty) {
        $context = $context->withTty($tty);
    }

    if (null !== $pty) {
        $context = $context->withPty($pty);
    }

    if (null !== $timeout) {
        $context = $context->withTimeout($timeout);
    }

    if (null !== $quiet) {
        $context = $context->withQuiet($quiet);
    }

    if (null !== $allowFailure) {
        $context = $context->withAllowFailure($allowFailure);
    }

    if (null !== $notify) {
        $context = $context->withNotify($notify);
    }

    if (\is_array($command)) {
        $process = new Process($command, $context->currentDirectory, $context->environment, null, $context->timeout);
    } else {
        $process = Process::fromShellCommandline($command, $context->currentDirectory, $context->environment, null, $context->timeout);
    }

    if ($context->tty) {
        $process->setTty(true);
        $process->setInput(\STDIN);
    } elseif ($context->pty) {
        $process->setPty(true);
        $process->setInput(\STDIN);
    }

    if (!$context->quiet && !$callback) {
        $callback = static function ($type, $bytes) {
            if (Process::OUT === $type) {
                fwrite(\STDOUT, $bytes);
            } else {
                fwrite(\STDERR, $bytes);
            }
        };
    }

    log('Running command: ' . $process->getCommandLine(), 'debug');

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

    if ($context->notify) {
        notify(sprintf('The command "%s" has been finished %s.', $process->getCommandLine(), 0 === $exitCode ? 'successfully' : 'with an error'));
    }

    if (0 !== $exitCode && !$context->allowFailure) {
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
    $context ??= ContextRegistry::getInitialContext();
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

function fs(): Filesystem
{
    static $filesystem;

    $filesystem ??= new Filesystem();

    return $filesystem;
}

function import(string $path): void
{
    if (!FunctionFinder::isInFindFunctions()) {
        throw new \LogicException('The import function cannot be dynamically invoked, use it a the root of the PHP file.');
    }

    if (!file_exists($path)) {
        throw new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $path));
    }

    if (is_file($path)) {
        castor_require($path);
    }

    if (is_dir($path)) {
        $files = Finder::create()
            ->files()
            ->name('*.php')
            ->in($path)
        ;

        foreach ($files as $file) {
            castor_require($file->getRealPath());
        }
    }
}
