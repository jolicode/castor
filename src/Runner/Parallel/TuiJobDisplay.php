<?php

namespace Castor\Runner\Parallel;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Shows one block per job: a spinner, its name, its duration, and the last
 * lines it printed. Once all the jobs are finished, the full output of the
 * failed ones is printed.
 *
 * @internal
 */
final class TuiJobDisplay implements JobDisplayInterface
{
    private const MAX_LINES = 5;
    private const REFRESH_INTERVAL = 0.05;
    // Only the colors are kept: the other sequences would move the cursor or
    // clear the screen behind the back of symfony/tui
    private const UNSAFE_SEQUENCES = '{\e\[[\x30-\x3F]*+[\x20-\x2F]*+[\x40-\x6C\x6E-\x7E]|\e\][^\a\e]*+(?:\a|\e\\\)?|\e(?![\[\]])|[\x00-\x08\x0A-\x1A\x1C-\x1F\x7F]}';

    /** @var array<string, resource> */
    private array $fullOutputs = [];
    /** @var array<string, Job> */
    private array $jobs = [];
    /** @var array<string, list<string>> The last non-blank lines of each job */
    private array $lastLines = [];
    /** @var array<string, JobLoaderWidget> */
    private array $loaders = [];
    /** @var array<string, TextWidget> */
    private array $logs = [];
    private readonly JobNameColumn $nameColumn;
    /** @var array<int, callable|int> The handlers of the signals interrupting Castor */
    private array $previousSignalHandlers = [];
    private float $refreshedAt = 0.0;
    private readonly Tui $tui;

    /**
     * @param non-empty-list<string> $names
     */
    public function __construct(
        private readonly StreamOutput $output,
        OutputInterface $errorOutput,
        array $names,
    ) {
        $this->nameColumn = new JobNameColumn($names);

        $this->tui = new Tui(
            styleSheet: new StyleSheet([
                '.failed::message' => new Style()->withColor('red'),
                '.failed::spinner' => new Style()->withColor('red'),
                '.log' => new Style()->withColor('gray'),
                '.succeeded::message' => new Style()->withColor('default'),
                '.succeeded::spinner' => new Style()->withColor('green'),
            ]),
            terminal: new OutputTerminal($output->getStream()),
        );

        foreach ($names as $name) {
            $this->jobs[$name] = new Job($name, $output, $errorOutput, $this->addLine(...), $this->addLine(...));
            $this->loaders[$name] = new JobLoaderWidget($this->nameColumn->format($name));
            $this->logs[$name] = new TextWidget(truncate: true);
            $this->logs[$name]->addStyleClass('log');
            $this->lastLines[$name] = [];
            // Kept on disk once large
            $this->fullOutputs[$name] = fopen('php://temp', 'w+') ?: throw new \RuntimeException('Could not open a temporary stream to keep the output of the job.');

            $this->tui->add($this->loaders[$name]);
            $this->tui->add($this->logs[$name]);
            $this->loaders[$name]->start();
        }

        // Driven by tick(), never by run(): the jobs are fibers of their own,
        // not callbacks of the event loop of symfony/tui (Revolt)
        $this->tui->start();
        $this->trapInterruptions();
    }

    /**
     * The blocks are redrawn in place, which only works in an interactive
     * terminal of a known size, tall enough to show them all. They would also
     * mix with the logs printed in verbose mode, and nothing should be drawn
     * in quiet mode.
     *
     * @phpstan-assert-if-true StreamOutput $output
     */
    public static function isSupported(OutputInterface $output, int $count): bool
    {
        return $output instanceof StreamOutput
            && $output->isDecorated()
            && OutputInterface::VERBOSITY_NORMAL === $output->getVerbosity()
            && stream_isatty($output->getStream())
            // The size of the terminal is read from it
            && stream_isatty(\STDIN)
            && false === getenv('CI')
            && $count < new OutputTerminal($output->getStream())->getRows();
    }

    public function getJob(string $name): Job
    {
        return $this->jobs[$name];
    }

    public function tick(): void
    {
        if (microtime(true) - $this->refreshedAt < self::REFRESH_INTERVAL) {
            return;
        }

        $this->refreshedAt = microtime(true);

        foreach ($this->jobs as $name => $job) {
            if ($job->isFinished()) {
                continue;
            }

            $this->loaders[$name]->setMessage(\sprintf('%s  %.1fs', $this->nameColumn->format($name), $job->getDuration()));
            $this->logs[$name]->setText($this->formatLastLines($job));
        }

        $this->tui->tick();
    }

    public function finish(Job $job): void
    {
        $name = $job->name;
        $duration = number_format($job->getDuration(), 1);
        $loader = $this->loaders[$name];

        if ($job->isSuccessful()) {
            $loader->addStyleClass('succeeded');
            $loader->setFinishedIndicator('✔');
            $loader->setMessage("{$this->nameColumn->format($name)}  {$duration}s");
            $this->logs[$name]->setText('');
        } else {
            $loader->addStyleClass('failed');
            $loader->setFinishedIndicator('✘');
            $loader->setMessage("{$this->nameColumn->format($name)}  failed after {$duration}s");
            // The last lines of a failure usually hold the error
            $this->logs[$name]->setText($this->formatLastLines($job));
        }

        $loader->stop();
    }

    public function stop(): void
    {
        try {
            $this->tui->requestRender();
            $this->tui->processRender();
        } finally {
            $this->tui->stop();
            $this->releaseInterruptions();
        }

        // The blocks were written behind the back of io(), which would not
        // separate them from what comes next
        $this->output->writeln('');

        foreach ($this->jobs as $name => $job) {
            if (!$job->isSuccessful()) {
                $this->output->writeln(\sprintf('<error> %s failed, its full output: </error>', OutputFormatter::escape($name)));
                rewind($this->fullOutputs[$name]);
                while (false !== $line = fgets($this->fullOutputs[$name])) {
                    $this->output->write($line, false, OutputInterface::OUTPUT_RAW);
                }
                $this->output->writeln('');
            }

            fclose($this->fullOutputs[$name]);
        }
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
        $this->tui->stop();
        $this->releaseInterruptions();

        if (\is_callable($previousHandler)) {
            $previousHandler($signal);

            return;
        }

        exit(128 + $signal);
    }

    private function addLine(Job $job, string $line): void
    {
        fwrite($this->fullOutputs[$job->name], $line . "\n");

        // The block shows what the job printed while it ran, its error is in
        // its full output
        if ($job->isFinished() || '' === trim($line)) {
            return;
        }

        $this->lastLines[$job->name][] = $line;
        if (\count($this->lastLines[$job->name]) > self::MAX_LINES) {
            array_shift($this->lastLines[$job->name]);
        }
    }

    private function formatLastLines(Job $job): string
    {
        $count = \count($this->jobs);
        // Keep all the blocks on the screen, they could not be redrawn otherwise
        $maxLines = min(self::MAX_LINES, intdiv($this->tui->getTerminal()->getRows() - 1 - $count, $count));
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
