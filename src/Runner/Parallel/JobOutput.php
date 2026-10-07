<?php

namespace Castor\Runner\Parallel;

use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Collects what is written to output() or io() inside a job, line by line.
 *
 * @internal
 */
final class JobOutput extends Output
{
    // Progress bars redraw the line they are on: they move back to its start
    // or clear it, and move up to the previous one when they span several
    // lines. They also report their progress to the terminal (OSC 9;4).
    private const LINE_RESETS = ["\r", "\e[G", "\e[1G", "\e[2K"];
    private const PROGRESS_PATTERNS = ['{\e\][^\a\e]*+(?:\a|\e\\\)}', '{\e\[\d*+[AB]}'];

    private string $pendingLine = '';

    /**
     * @param \Closure(string): void $onLine Called for each complete line
     */
    public function __construct(
        OutputInterface $parentOutput,
        private readonly \Closure $onLine,
    ) {
        parent::__construct($parentOutput->getVerbosity(), $parentOutput->isDecorated(), clone $parentOutput->getFormatter());
    }

    /**
     * The line being written, like the current state of a progress bar.
     */
    public function getPendingLine(): string
    {
        return self::keepLastState($this->pendingLine);
    }

    /**
     * Ends the line being written, once nothing else can be written.
     */
    public function flush(): void
    {
        if ('' === $this->pendingLine) {
            return;
        }

        ($this->onLine)(self::keepLastState($this->pendingLine));
        $this->pendingLine = '';
    }

    protected function doWrite(string $message, bool $newline): void
    {
        // The pending line is kept with its trailing "\r": the "\n" of a
        // "\r\n" can come in the next write
        $lines = explode("\n", str_replace("\r\n", "\n", $this->pendingLine . $message . ($newline ? "\n" : '')));
        $pendingLine = array_pop($lines);
        $this->pendingLine = self::keepLastState($pendingLine) . (str_ends_with($pendingLine, "\r") ? "\r" : '');

        foreach ($lines as $line) {
            ($this->onLine)(self::keepLastState($line));
        }
    }

    private static function keepLastState(string $line): string
    {
        $line = rtrim($line, "\r");

        // Without a regex on the whole line: it can be huge (minified JSON…)
        $start = 0;
        foreach (self::LINE_RESETS as $reset) {
            if (false !== $position = strrpos($line, $reset)) {
                $start = max($start, $position + \strlen($reset));
            }
        }

        return preg_replace(self::PROGRESS_PATTERNS, '', substr($line, $start))
            ?? throw new \LogicException(\sprintf('Could not remove the progress sequences from a line: %s.', preg_last_error_msg()));
    }
}
