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
    /**
     * The processes to forward a signal to.
     *
     * @var array<int, array<int, Process>>
     */
    private array $processes = [];

    /**
     * The handlers that were registered before Castor trapped a signal.
     *
     * @var array<int, callable|int>
     */
    private array $previousHandlers = [];

    private bool $unsupportedWarningSent = false;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function isSupported(): bool
    {
        return \function_exists('pcntl_signal')
            && \function_exists('pcntl_signal_get_handler')
            && \function_exists('pcntl_async_signals');
    }

    /**
     * @param int[] $signals
     */
    public function trap(Process $process, array $signals): void
    {
        if (!$signals) {
            return;
        }

        if (!self::isSupported()) {
            if (!$this->unsupportedWarningSent) {
                $this->unsupportedWarningSent = true;
                $this->logger->warning('Cannot trap signals: the "pcntl" extension is not available.');
            }

            return;
        }

        pcntl_async_signals(true);

        foreach ($signals as $signal) {
            if (!isset($this->processes[$signal])) {
                $this->processes[$signal] = [];

                $this->previousHandlers[$signal] = pcntl_signal_get_handler($signal);

                pcntl_signal($signal, $this->handle(...));
            }

            $this->processes[$signal][spl_object_id($process)] = $process;
        }

        $this->logger->debug(\sprintf('Trapping signals "%s" for the running process.', implode('", "', $signals)));
    }

    /**
     * @param int[] $signals
     */
    public function release(Process $process, array $signals): void
    {
        foreach ($signals as $signal) {
            if (!isset($this->processes[$signal])) {
                continue;
            }

            unset($this->processes[$signal][spl_object_id($process)]);

            if ($this->processes[$signal]) {
                continue;
            }

            unset($this->processes[$signal]);

            if (self::isSupported()) {
                pcntl_signal($signal, $this->previousHandlers[$signal] ?? \SIG_DFL);
            }

            unset($this->previousHandlers[$signal]);
        }
    }

    /**
     * @internal
     */
    public function handle(int $signal): void
    {
        foreach ($this->processes[$signal] ?? [] as $process) {
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
