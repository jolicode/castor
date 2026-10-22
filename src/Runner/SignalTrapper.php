<?php

namespace Castor\Runner;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Traps signals sent to Castor and forwards them to the processes currently
 * running, instead of letting them interrupt Castor itself.
 *
 * @internal
 */
class SignalTrapper
{
    // SIGINT (CTRL+C) and SIGQUIT (CTRL+\): \SIGINT and \SIGQUIT need pcntl
    private const TERMINAL_SIGNALS = [2, 3];

    /** @var array<int, array<int, Process>> */
    private array $processes = [];

    /** @var array<int, callable|int> */
    private array $previousHandlers = [];

    private bool $unsupportedWarningSent = false;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param int[] $signals
     */
    public function trap(Process $process, array $signals): void
    {
        if (!$signals) {
            return;
        }

        if (!$this->isSupported()) {
            if (!$this->unsupportedWarningSent) {
                $this->unsupportedWarningSent = true;
                $this->logger->warning('Cannot trap signals: the "pcntl" extension is not available.');
            }

            return;
        }

        $this->register($process, $signals);

        $this->logger->debug(\sprintf('Trapping signals "%s" for the running process.', implode('", "', $signals)));
    }

    /**
     * A process which inherits the standard streams of Castor shares its
     * terminal, which sends a CTRL+C or a CTRL+\ to both of them. Like a shell,
     * Castor survives these signals while the process runs: the process decides
     * what to do with them, and the runner then looks at how it ended.
     */
    public function shield(Process $process): void
    {
        // Without pcntl, these signals stop Castor and the process: the task stops anyway
        if (!$this->isSupported()) {
            return;
        }

        $this->register($process, self::TERMINAL_SIGNALS);
    }

    public function release(Process $process): void
    {
        foreach (array_keys($this->processes) as $signal) {
            unset($this->processes[$signal][spl_object_id($process)]);

            if ($this->processes[$signal]) {
                continue;
            }

            unset($this->processes[$signal]);

            if ($this->isSupported()) {
                pcntl_signal($signal, $this->previousHandlers[$signal] ?? \SIG_DFL);
            }

            unset($this->previousHandlers[$signal]);
        }
    }

    /**
     * @param int[] $signals
     */
    private function register(Process $process, array $signals): void
    {
        pcntl_async_signals(true);

        foreach ($signals as $signal) {
            if (!isset($this->processes[$signal])) {
                $this->processes[$signal] = [];

                $this->previousHandlers[$signal] = pcntl_signal_get_handler($signal);

                pcntl_signal($signal, $this->handle(...));
            }

            $this->processes[$signal][spl_object_id($process)] = $process;
        }
    }

    private function isSupported(): bool
    {
        return \function_exists('pcntl_signal')
            && \function_exists('pcntl_signal_get_handler')
            && \function_exists('pcntl_async_signals');
    }

    private function handle(int $signal): void
    {
        foreach ($this->processes[$signal] ?? [] as $process) {
            // The terminal already sent it to the process
            if ($process->isStdioInherited() && \in_array($signal, self::TERMINAL_SIGNALS, true)) {
                continue;
            }

            try {
                if (!$process->isRunning()) {
                    continue;
                }

                $process->signal($signal);
            } catch (\Throwable $e) {
                // The process may have finished between the isRunning() call and
                // the signal() one: there is nothing we can do about it.
                $this->logger->debug(\sprintf('Could not forward signal "%d" to the process: %s', $signal, $e->getMessage()));
            }
        }
    }
}
