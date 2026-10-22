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
use Castor\Exception\ProcessInterruptedException;
use Castor\Helper\Notifier;
use Castor\Runner\Parallel\JobRegistry;
use JoliCode\PhpOsHelper\OsHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

use function Symfony\Component\String\u;

/** @internal */
class ProcessRunner
{
    // \SIGINT needs the pcntl extension
    private const SIGINT = 2;

    public function __construct(
        private readonly ContextRegistry $contextRegistry,
        private readonly SectionOutput $sectionOutput,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Notifier $notifier,
        private readonly LoggerInterface $logger,
        private readonly SymfonyStyle $io,
        private readonly SignalTrapper $signalTrapper,
        private readonly JobRegistry $jobRegistry,
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

            $command = array_map(static fn ($part) => (string) $part, $command);

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

        // Inside a job of parallel(), the output is captured to be organized.
        // The tools must not draw for a terminal they do not own.
        $job = $this->jobRegistry->getCurrent();
        if ($job && !$context->quiet && !$callback) {
            if ($context->tty) {
                $this->logger->warning(\sprintf('The command "%s" runs without TTY: the output of a job of parallel() is captured.', u($process->getCommandLine())->truncate(40, '...')));
            }

            $context = $context
                ->withTty(false)
                ->withPty(false)
            ;
            $callback = static function (string $type, string $bytes) use ($job): void {
                $output = Process::ERR === $type ? $job->errorOutput : $job->output;
                // Shown even in quiet mode, like when it is not in a job
                $output->write($bytes, false, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
            };
        }

        // The process inherits the standard input, output and error of Castor,
        // when nothing reads its output: it then behaves as if it was run from
        // a shell (terminal detection, redirections, CTRL+C, ...)
        $stdioInherited = $context->stdioInherited && !$context->quiet && !$callback && !$this->sectionOutput->usesOutputSection();

        if ($stdioInherited) {
            $process->inheritStdio();

            // Only the standard input is replaced, the output stays inherited
            if (null !== $context->input) {
                $process->setInput($context->input);
            }
        } elseif (null !== $context->input) {
            // When input is provided, we need to disable TTY and PTY because they require interactive terminal
            $process->setInput($context->input);
        } elseif (!OsHelper::isWindows()) {
            // TTY does not work on windows, and PTY is a mess, so let's skip everything!
            if ($context->tty) {
                $process->setTty(true);
                $process->setInput(\STDIN);
            } elseif ($context->pty) {
                if (SizedPtyProcess::hasTerminal()) {
                    $process = SizedPtyProcess::create($command, $context);
                }
                $process->setPty(true);
                $process->setInput(\STDIN);
            }
        }

        if (!$stdioInherited && !$context->quiet && !$callback) {
            $callback = function ($type, $bytes, $process): void {
                $this->sectionOutput->writeProcessOutput($type, $bytes, $process);
            };
        }

        $this->eventDispatcher->dispatch(new ProcessCreatedEvent($process));

        // The arguments may not be valid UTF-8, which symfony/string cannot truncate
        $this->logger->notice(\sprintf('Running command: "%s".', u(mb_scrub($process->getCommandLine(), 'UTF-8'))->truncate(40, '...')), [
            'process' => $process,
        ]);

        if (!$job) {
            $this->sectionOutput->initProcess($process);
        }

        $process->start(static function ($type, $bytes) use ($callback, $process): void {
            if ($callback) {
                $callback($type, $bytes, $process);
            }
        });

        $this->signalTrapper->trap($process, $context->trappedSignals);
        if ($stdioInherited) {
            $this->signalTrapper->shield($process);
        }

        $this->eventDispatcher->dispatch(new ProcessStartEvent($process));

        $interruption = null;

        try {
            if (\Fiber::getCurrent()) {
                while ($process->isRunning()) {
                    if (!$job) {
                        $this->sectionOutput->tickProcess($process);
                    }
                    $process->checkTimeout();
                    // parallel() pauses once all its fibers had their turn
                    \Fiber::suspend();
                }
            }

            $exitCode = $process->wait();
        } catch (ProcessSignaledException $e) {
            if (!\in_array($e->getSignal(), $context->trappedSignals, true)) {
                // Castor would already have stopped if it had received this
                // SIGINT too: the task stops as if it had, once the signals
                // trapped for the process are released
                if (self::SIGINT !== $e->getSignal()) {
                    throw $e;
                }

                $interruption = $e;
            }

            $exitCode = $process->getExitCode() ?? 128 + $e->getSignal();
        } finally {
            $this->signalTrapper->release($process);
            if (!$job) {
                $this->sectionOutput->finishProcess($process);
            }
            $this->eventDispatcher->dispatch(new ProcessTerminateEvent($process));
        }

        if ($interruption) {
            $this->interrupt($process, $interruption);
        }

        if ($context->notify) {
            $this->notifier->send(\sprintf('The command "%s" has been finished %s.', $process->getCommandLine(), 0 === $exitCode ? 'successfully' : 'with an error'));
        }

        if (0 !== $exitCode) {
            $this->logger->notice(\sprintf('Command finished with an error (exit code=%d).', $process->getExitCode()));

            if ($this->isInterruptedInTerminal($process, $exitCode, $context)) {
                $this->interrupt($process);
            }

            // Nobody can answer the question while the output of a job is captured
            if ($context->verboseArguments && !$context->verbosityLevel->isVerbose() && !$job) {
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
            if (false === $value || 'argv' === $key || 'argc' === $key) {
                continue;
            }
            $runnable = \sprintf('%s=%s %s ', $key, escapeshellarg((string) $value), $runnable);
        }

        return rtrim($runnable, ' ');
    }

    /**
     * Stops Castor as if it had received the CTRL+C, like a shell does when a
     * command dies from it: Symfony Console calls the onSignals handlers of the
     * task, and exits with the code 130 (128 + SIGINT). A catch in the task
     * cannot carry on after it.
     *
     * The exception stops the task when the signal does not stop Castor: an
     * onSignals handler asked to go on, SIGINT is ignored (Castor run in the
     * background by a script), or posix is missing (Windows).
     */
    private function interrupt(Process $process, ?ProcessSignaledException $previous = null): never
    {
        if (\function_exists('posix_kill')) {
            posix_kill(posix_getpid(), self::SIGINT);
        }

        throw new ProcessInterruptedException($process, $previous);
    }

    /**
     * A CTRL+C usually stops Castor along with the process. But a process with
     * the terminal (TTY) may put it in raw mode (docker run -it, vim, ...): the
     * CTRL+C only reaches this process, and its exit code 130 (128 + SIGINT)
     * is the only trace of it. A process which inherits the standard streams
     * of Castor gets the CTRL+C too, but Castor survives it and lets the
     * process decide: same trace. Not when SIGINT is trapped: the task goes on.
     *
     * Symfony Console, which exits with 128 + the number of the signal it
     * receives, can't see these CTRL+C: Castor receives no signal with the TTY,
     * and survives it when it inherits the standard streams.
     */
    private function isInterruptedInTerminal(Process $process, int $exitCode, Context $context): bool
    {
        return 128 + self::SIGINT === $exitCode
            && ($process->isTty() || $process->isStdioInherited())
            && !\in_array(self::SIGINT, $context->trappedSignals, true);
    }
}
