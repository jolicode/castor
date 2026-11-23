<?php

namespace Castor\Runner\Job;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Shows one block per job: a spinner, its name, its duration, and the last
 * lines it printed. Once the command is finished, the full output of the
 * failed jobs is printed.
 *
 * The blocks are drawn in a region at the bottom of the screen, redrawn in
 * place. A job that runs again reuses its block while it is in this region.
 * When the region would not fit on the screen, or when some code outside of
 * the jobs writes to the console, the blocks of the finished jobs are left
 * above, where they can not be redrawn anymore: a job that runs again after
 * that gets a new block, at the bottom.
 *
 * @internal
 */
final class TuiJobDisplay implements JobDisplayInterface
{
    private const MAX_LINES = 5;
    private const REFRESH_INTERVAL = 0.05;
    // While the code outside of the jobs writes, the blocks would be drawn
    // again after each of its lines
    private const RESUME_DELAY = 0.2;
    // Only the colors are kept: the other sequences would move the cursor or
    // clear the screen behind the back of symfony/tui
    private const UNSAFE_SEQUENCES = '{\e\[[\x30-\x3F]*+[\x20-\x2F]*+[\x40-\x6C\x6E-\x7E]|\e\][^\a\e]*+(?:\a|\e\\\)?|\e(?![\[\]])|[\x00-\x08\x0A-\x1A\x1C-\x1F\x7F]}';

    /** @var array<string, array{loader: JobLoaderWidget, log: TextWidget}> The blocks drawn in the region, in order */
    private array $blocks = [];
    /** @var array<string, resource> */
    private array $fullOutputs = [];
    /** @var array<string, list<string>> The last non-blank lines of each job */
    private array $lastLines = [];
    private readonly JobNameColumn $nameColumn;
    /** @var array<string, true> The jobs to draw in the region, in order */
    private array $pending = [];
    /** @var array<int, callable|int> The handlers of the signals interrupting Castor */
    private array $previousSignalHandlers = [];
    private float $refreshedAt = 0.0;
    /** @var array<string, Job> */
    private array $jobs = [];
    private ?float $suspendedAt = null;
    private readonly OutputTerminal $terminal;
    private ?Tui $tui = null;

    public function __construct(
        private readonly StreamOutput $output,
    ) {
        $this->nameColumn = new JobNameColumn();
        $this->terminal = new OutputTerminal($output->getStream());
    }

    /**
     * The blocks are redrawn in place, which only works in an interactive
     * terminal of a known size. They would also mix with the logs printed in
     * verbose mode, and nothing should be drawn in quiet mode.
     *
     * @phpstan-assert-if-true StreamOutput $output
     */
    public static function isSupported(OutputInterface $output): bool
    {
        return $output instanceof StreamOutput
            && $output->isDecorated()
            && OutputInterface::VERBOSITY_NORMAL === $output->getVerbosity()
            && stream_isatty($output->getStream())
            // The size of the terminal is read from it
            && stream_isatty(\STDIN)
            && false === getenv('CI')
            // Room for a block, and the line of the cursor
            && new OutputTerminal($output->getStream())->getRows() > 2;
    }

    public function add(Job $job): void
    {
        $this->jobs[$job->name] = $job;
        $this->nameColumn->add($job->name);
        $this->lastLines[$job->name] = [];
        // Kept on disk once large
        $this->fullOutputs[$job->name] = fopen('php://temp', 'w+') ?: throw new \RuntimeException('Could not open a temporary stream to keep the output of the job.');
    }

    public function addLine(Job $job, string $line, bool $isError): void
    {
        fwrite($this->fullOutputs[$job->name], $line . "\n");

        // The block shows what the job printed while it ran, its error is in
        // its full output
        if ((!$job->isRunning() && $job->hasFailed()) || '' === trim($line)) {
            return;
        }

        $this->lastLines[$job->name][] = $line;
        if (\count($this->lastLines[$job->name]) > self::MAX_LINES) {
            array_shift($this->lastLines[$job->name]);
        }
    }

    public function update(Job $job): void
    {
        if (isset($this->blocks[$job->name])) {
            $this->drawState($job);
        } else {
            $this->pending[$job->name] = true;
        }

        $this->drawPending();
    }

    public function tick(): void
    {
        $this->drawPending();

        if (!$this->tui || microtime(true) - $this->refreshedAt < self::REFRESH_INTERVAL) {
            return;
        }

        $this->refreshedAt = microtime(true);

        foreach ($this->blocks as $name => $block) {
            $job = $this->jobs[$name];
            if (!$job->isRunning()) {
                continue;
            }

            $block['loader']->setMessage(\sprintf('%s  %.1fs', $this->nameColumn->format($name), $job->getDuration()));
            $block['log']->setText($this->formatLastLines($job));
        }

        // The texts do not request it themselves
        $this->tui->requestRender();
        $this->tui->tick();
    }

    public function suspend(): void
    {
        $this->suspendedAt = microtime(true);
        $this->leaveFinishedBlocks(false);
    }

    public function stop(): void
    {
        // The jobs that ran while the display was suspended are shown too
        $this->suspendedAt = null;
        $this->drawPending();
        $this->leaveFinishedBlocks(false);

        $failedJobs = array_filter($this->jobs, static fn (Job $job) => $job->hasFailed());
        if ($failedJobs) {
            // The blocks were written behind the back of io(), which would
            // not separate them from what comes next
            $this->output->writeln('');
        }

        foreach ($this->jobs as $name => $job) {
            if ($job->hasFailed()) {
                $this->output->writeln(\sprintf('<error> %s failed, its full output: </error>', OutputFormatter::escape($name)));
                rewind($this->fullOutputs[$name]);
                while (false !== $line = fgets($this->fullOutputs[$name])) {
                    $this->output->write($line, false, OutputInterface::OUTPUT_RAW);
                }
                $this->output->writeln('');
            }

            fclose($this->fullOutputs[$name]);
        }

        $this->jobs = $this->fullOutputs = $this->lastLines = $this->pending = [];
    }

    /**
     * Adds the blocks of the jobs waiting for one at the bottom of the
     * region, as long as it fits on the screen.
     */
    private function drawPending(): void
    {
        if (!$this->pending) {
            return;
        }

        if (null !== $this->suspendedAt) {
            if (microtime(true) - $this->suspendedAt < self::RESUME_DELAY) {
                return;
            }

            $this->suspendedAt = null;
        }

        foreach (array_keys($this->pending) as $name) {
            if (\count($this->blocks) >= $this->getCapacity()) {
                $this->leaveFinishedBlocks(true);

                // Too many jobs run at the same time: the next ones wait
                // for a free place
                if (\count($this->blocks) >= $this->getCapacity()) {
                    break;
                }
            }

            unset($this->pending[$name]);

            $loader = new JobLoaderWidget($this->nameColumn->format($name));
            $log = new TextWidget(truncate: true);
            $log->addStyleClass('log');
            $this->blocks[$name] = ['loader' => $loader, 'log' => $log];

            $this->startTui()->add($loader)->add($log);
            $this->drawState($this->jobs[$name]);
        }

        $this->tui?->requestRender();
        $this->tui?->processRender();
    }

    /**
     * Leaves the blocks of the finished jobs above the region, where they
     * stay as they are.
     *
     * @param bool $keepRunning Whether the blocks of the running jobs are
     *                          drawn again below, or wait to be
     */
    private function leaveFinishedBlocks(bool $keepRunning): void
    {
        if (!$this->tui) {
            return;
        }

        $running = [];
        foreach ($this->blocks as $name => $block) {
            if ($this->jobs[$name]->isRunning()) {
                $running[$name] = $block;
                $this->tui->remove($block['loader'])->remove($block['log']);
            }
        }

        try {
            $this->tui->requestRender();
            $this->tui->processRender();
        } finally {
            $this->stopTui();
        }

        $this->blocks = [];

        if (!$keepRunning) {
            // Before the jobs that wait for a block, they were drawn first
            $this->pending = array_fill_keys(array_keys($running), true) + $this->pending;

            return;
        }

        foreach ($running as $name => $block) {
            $this->blocks[$name] = $block;
            $this->startTui()->add($block['loader'])->add($block['log']);
        }
    }

    private function drawState(Job $job): void
    {
        $name = $job->name;
        $loader = $this->blocks[$name]['loader'];
        $log = $this->blocks[$name]['log'];
        $duration = number_format($job->getDuration(), 1);

        $loader->removeStyleClass('succeeded')->removeStyleClass('failed');

        if ($job->isRunning()) {
            $loader->setMessage("{$this->nameColumn->format($name)}  {$duration}s");
            $log->setText($this->formatLastLines($job));
            $loader->start();

            return;
        }

        if ($job->hasFailed()) {
            $loader->addStyleClass('failed');
            $loader->setFinishedIndicator('✘');
            $loader->setMessage("{$this->nameColumn->format($name)}  failed after {$duration}s");
            // The last lines of a failure usually hold the error
            $log->setText($this->formatLastLines($job));
        } else {
            $loader->addStyleClass('succeeded');
            $loader->setFinishedIndicator('✔');
            $loader->setMessage("{$this->nameColumn->format($name)}  {$duration}s");
            $log->setText('');
        }

        $loader->stop();
    }

    private function startTui(): Tui
    {
        if ($this->tui) {
            return $this->tui;
        }

        $tui = $this->tui = new Tui(
            styleSheet: new StyleSheet([
                '.failed::message' => new Style()->withColor('red'),
                '.failed::spinner' => new Style()->withColor('red'),
                '.log' => new Style()->withColor('gray'),
                '.succeeded::message' => new Style()->withColor('default'),
                '.succeeded::spinner' => new Style()->withColor('green'),
            ]),
            terminal: $this->terminal,
        );

        // Driven by tick(), never by run(): the jobs are not callbacks of
        // the event loop of symfony/tui (Revolt)
        $tui->start();
        $this->trapInterruptions();

        return $tui;
    }

    private function stopTui(): void
    {
        $this->tui?->stop();
        $this->tui = null;
        $this->releaseInterruptions();
    }

    /**
     * The number of blocks the region can hold, with one line each, and the
     * line of the cursor.
     */
    private function getCapacity(): int
    {
        return max(1, $this->terminal->getRows() - 2);
    }

    private function trapInterruptions(): void
    {
        // Not available on Windows
        if (!\function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([\SIGINT, \SIGTERM] as $signal) {
            $handler = pcntl_signal_get_handler($signal);
            if (\SIG_IGN === $handler) {
                continue;
            }

            $this->previousSignalHandlers[$signal] = $handler;
            pcntl_signal($signal, $this->interrupt(...));
        }
    }

    private function releaseInterruptions(): void
    {
        foreach ($this->previousSignalHandlers as $signal => $handler) {
            pcntl_signal($signal, $handler);
        }

        $this->previousSignalHandlers = [];
    }

    /**
     * Moves below the blocks, where the prompt of the shell goes once Castor
     * is interrupted, then handles the signal as it would have been.
     */
    private function interrupt(int $signal): void
    {
        $previousHandler = $this->previousSignalHandlers[$signal];
        $this->tui?->stop();
        $this->tui = null;
        $this->releaseInterruptions();

        if (\is_callable($previousHandler)) {
            $previousHandler($signal);

            return;
        }

        exit(128 + $signal);
    }

    private function formatLastLines(Job $job): string
    {
        $count = max(1, \count($this->blocks));
        // Keep all the blocks on the screen, they could not be redrawn otherwise
        $maxLines = min(self::MAX_LINES, intdiv($this->terminal->getRows() - 1 - $count, $count));
        if ($maxLines <= 0) {
            return '';
        }

        // The lines being written show the progress bars
        $pendingLines = array_filter(
            [$job->output->getPendingLine(), $job->errorOutput->getPendingLine()],
            static fn (string $line) => '' !== trim($line),
        );

        return implode("\n", array_map(
            static fn (string $line) => '    ' . self::removeUnsafeSequences($line),
            \array_slice([...$this->lastLines[$job->name], ...$pendingLines], -$maxLines),
        ));
    }

    private static function removeUnsafeSequences(string $line): string
    {
        $line = preg_replace(self::UNSAFE_SEQUENCES, '', $line)
            ?? throw new \LogicException(\sprintf('Could not remove the unsafe sequences from a line: %s.', preg_last_error_msg()));

        // symfony/tui cannot measure the width of invalid UTF-8
        return mb_scrub($line, 'UTF-8');
    }
}
