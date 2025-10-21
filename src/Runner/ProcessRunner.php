<?php

namespace Castor\Runner;

use Castor\CommandBuilder\CommandBuilderInterface;
use Castor\CommandBuilder\ContextUpdaterInterface;
use Castor\Console\Output\SectionOutput;
use Castor\Console\Output\VerbosityLevel;
use Castor\Context;
use Castor\ContextRegistry;
use Castor\Event\ProcessCreatedEvent;
use Castor\Event\ProcessStartEvent;
use Castor\Event\ProcessTerminateEvent;
use Castor\Helper\Notifier;
use JoliCode\PhpOsHelper\OsHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function Symfony\Component\String\u;

/** @internal */
class ProcessRunner
{
    public function __construct(
        private readonly ContextRegistry $contextRegistry,
        private readonly SectionOutput $sectionOutput,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Notifier $notifier,
        private readonly LoggerInterface $logger,
        private readonly SymfonyStyle $io,
    ) {
    }

    /**
     * @param string|array<string|\Stringable|int>|CommandBuilderInterface $command
     * @param (callable(string, string, Process) :void)|null               $callback
     */
    public function run(
        string|array|CommandBuilderInterface $command,
        ?Context $context = null,
        ?callable $callback = null,
    ): Process {
        $context ??= $this->contextRegistry->getCurrentContext();

        if ($command instanceof CommandBuilderInterface) {
            if ($command instanceof ContextUpdaterInterface) {
                $context = $command->updateContext($context);
            }

            $command = $command->getCommand();
        }

        if (\is_array($command)) {
            if ($context->verbosityLevel->isVerbose() && $context->verboseArguments) {
                $command = array_merge($command, $context->verboseArguments);
            }

            $process = new Process($command, $context->workingDirectory, $context->environment, null, $context->timeout);
        } else {
            if ($context->verbosityLevel->isVerbose() && $context->verboseArguments) {
                $command = \sprintf('%s %s', $command, implode(' ', $context->verboseArguments));
            }

            $process = Process::fromShellCommandline($command, $context->workingDirectory, $context->environment, null, $context->timeout);
        }

        // When quiet is set, it means we want to capture the output.
        // So we disable TTY and PTY because it does not make sens otherwise (and it's buggy).
        if ($context->quiet) {
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
            $callback = function ($type, $bytes, $process): void {
                $this->sectionOutput->writeProcessOutput($type, $bytes, $process);
            };
        }

        $this->eventDispatcher->dispatch(new ProcessCreatedEvent($process));

        $this->logger->notice(\sprintf('Running command: "%s".', u($process->getCommandLine())->truncate(40, '...')), [
            'process' => $process,
        ]);

        $this->sectionOutput->initProcess($process);

        $process->start(function ($type, $bytes) use ($callback, $process): void {
            if ($callback) {
                $callback($type, $bytes, $process);
            }
        });

        $this->eventDispatcher->dispatch(new ProcessStartEvent($process));

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
            $this->eventDispatcher->dispatch(new ProcessTerminateEvent($process));
        }

        if ($context->notify) {
            $this->notifier->send(\sprintf('The command "%s" has been finished %s.', $process->getCommandLine(), 0 === $exitCode ? 'successfully' : 'with an error'));
        }

        if (0 !== $exitCode) {
            $this->logger->notice(\sprintf('Command finished with an error (exit code=%d).', $process->getExitCode()));

            if ($context->verboseArguments && !$context->verbosityLevel->isVerbose()) {
                $retry = $this->io->confirm('Do you want to retry the command with verbose arguments?', false);

                if ($retry) {
                    return $this->run(
                        command: $command,
                        context: $context->withVerbosityLevel(VerbosityLevel::VERBOSE),
                        callback: $callback,
                    );
                }
            }

            if (!$context->allowFailure) {
                throw new ProcessFailedException($process);
            }

            return $process;
        }

        $this->logger->debug('Command finished successfully.');

        return $process;
    }

    /**
     * @param string|array<string|\Stringable|int> $command
     */
    public function capture(
        string|array $command,
        ?Context $context = null,
        ?string $onFailure = null,
    ): string {
        $hasOnFailure = null !== $onFailure;
        $context ??= $this->contextRegistry->getCurrentContext();

        if ($hasOnFailure) {
            $context = $context->withAllowFailure();
        }

        $process = $this->run(
            command: $command,
            context: $context->withQuiet(),
        );

        if ($hasOnFailure && !$process->isSuccessful()) {
            return $onFailure;
        }

        return trim($process->getOutput());
    }

    /**
     * @param string|array<string|\Stringable|int> $command
     */
    public function exitCode(
        string|array $command,
        ?Context $context = null,
    ): int {
        $process = $this->run(
            command: $command,
            context: ($context ?? $this->contextRegistry->getCurrentContext())->withAllowFailure(),
        );

        return $process->getExitCode() ?? 0;
    }

    public function buildRunnableCommand(Process $process): string
    {
        $runnable = $process->getCommandLine();

        foreach ($process->getEnv() as $key => $value) {
            if (null === $value || 'argv' === $key || 'argc' === $key) {
                continue;
            }
            $runnable = \sprintf('%s=%s %s ', $key, escapeshellarg((string) $value), $runnable);
        }

        return rtrim($runnable, ' ');
    }
}
