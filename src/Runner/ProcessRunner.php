<?php

namespace Castor\Runner;

use Castor\Console\Output\SectionOutput;
use Castor\Context;
use Castor\ContextRegistry;
use Castor\Event;
use Castor\Helper\Notifier;
use JoliCode\PhpOsHelper\OsHelper;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function Castor\Internal\fix_exception;

class ProcessRunner
{
    public function __construct(
        private readonly ContextRegistry $contextRegistry,
        private readonly SectionOutput $sectionOutput,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Notifier $notifier,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param string|array<string|\Stringable|int>           $command
     * @param array<string, string|\Stringable|int>|null     $environment
     * @param (callable(string, string, Process) :void)|null $callback
     */
    public function run(
        string|array $command,
        ?array $environment = null,
        ?string $workingDirectory = null,
        ?bool $tty = null,
        ?bool $pty = null,
        ?float $timeout = null,
        ?bool $quiet = null,
        ?bool $allowFailure = null,
        ?bool $notify = null,
        ?callable $callback = null,
        ?Context $context = null,
    ): Process {
        $context ??= $this->contextRegistry->getCurrentContext();

        if (null !== $environment) {
            $context = $context->withEnvironment($environment);
        }

        if ($workingDirectory) {
            $context = $context->withWorkingDirectory($workingDirectory);
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
            $process = new Process($command, $context->workingDirectory, $context->environment, null, $context->timeout);
        } else {
            $process = Process::fromShellCommandline($command, $context->workingDirectory, $context->environment, null, $context->timeout);
        }

        // When quiet is set, it means we want to capture the output.
        // So we disable TTY and PTY because it does not make sens otherwise (and it's buggy).
        if ($context->quiet) {
            if ($tty) {
                throw new \LogicException('The "tty" argument cannot be used with "quiet".');
            }
            if ($pty) {
                throw new \LogicException('The "pty" argument cannot be used with "quiet".');
            }
            $context = $context
                ->withTty(false)
                ->withPty(false)
            ;
        }

        // TTY does not work on windows, and PTY is a mess, so let's skip everything!
        if (!OsHelper::isWindows()) {
            if ($context->tty) {
                $process->setTty(true);
                $process->setInput(\STDIN);
            } elseif ($context->pty) {
                $process->setPty(true);
                $process->setInput(\STDIN);
            }
        }

        if (!$context->quiet && !$callback) {
            $callback = function ($type, $bytes, $process) {
                $this->sectionOutput->writeProcessOutput($type, $bytes, $process);
            };
        }

        $this->eventDispatcher->dispatch(new Event\ProcessCreatedEvent($process));

        $this->logger->info(\sprintf('Running command: "%s".', $process->getCommandLine()), [
            'process' => $process,
        ]);

        $this->sectionOutput->initProcess($process);

        $process->start(function ($type, $bytes) use ($callback, $process) {
            if ($callback) {
                $callback($type, $bytes, $process);
            }
        });

        $this->eventDispatcher->dispatch(new Event\ProcessStartEvent($process));

        if (\Fiber::getCurrent()) {
            while ($process->isRunning()) {
                $this->sectionOutput->tickProcess($process);
                \Fiber::suspend();
                usleep(20_000);
            }
        }

        try {
            $exitCode = $process->wait();
        } finally {
            $this->sectionOutput->finishProcess($process);
            $this->eventDispatcher->dispatch(new Event\ProcessTerminateEvent($process));
        }

        if ($context->notify) {
            $this->notifier->send(\sprintf('The command "%s" has been finished %s.', $process->getCommandLine(), 0 === $exitCode ? 'successfully' : 'with an error'));
        }

        if (0 !== $exitCode) {
            $this->logger->notice(\sprintf('Command finished with an error (exit code=%d).', $process->getExitCode()));
            if (!$context->allowFailure) {
                if ($context->verbosityLevel->isVerbose()) {
                    throw new ProcessFailedException($process);
                }

                throw fix_exception(new \Exception("The command \"{$process->getCommandLine()}\" failed."), 1);
            }

            return $process;
        }

        $this->logger->debug('Command finished successfully.');

        return $process;
    }

    /**
     * @param string|array<string|\Stringable|int>       $command
     * @param array<string, string|\Stringable|int>|null $environment
     */
    public function capture(
        string|array $command,
        ?array $environment = null,
        ?string $workingDirectory = null,
        ?float $timeout = null,
        ?bool $allowFailure = null,
        ?string $onFailure = null,
        ?Context $context = null,
    ): string {
        $hasOnFailure = null !== $onFailure;
        if ($hasOnFailure) {
            if (null !== $allowFailure) {
                throw new \LogicException('The "allowFailure" argument cannot be used with "onFailure".');
            }
            $allowFailure = true;
        }

        $process = $this->run(
            command: $command,
            environment: $environment,
            workingDirectory: $workingDirectory,
            timeout: $timeout,
            allowFailure: $allowFailure,
            context: $context,
            quiet: true,
        );

        if ($hasOnFailure && !$process->isSuccessful()) {
            return $onFailure;
        }

        return trim($process->getOutput());
    }

    /**
     * @param string|array<string|\Stringable|int>       $command
     * @param array<string, string|\Stringable|int>|null $environment
     */
    public function exitCode(
        string|array $command,
        ?array $environment = null,
        ?string $workingDirectory = null,
        ?float $timeout = null,
        ?bool $quiet = null,
        ?Context $context = null,
    ): int {
        $process = $this->run(
            command: $command,
            environment: $environment,
            workingDirectory: $workingDirectory,
            timeout: $timeout,
            allowFailure: true,
            context: $context,
            quiet: $quiet,
        );

        return $process->getExitCode() ?? 0;
    }
}
