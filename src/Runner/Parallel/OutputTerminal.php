<?php

namespace Castor\Runner\Parallel;

use Symfony\Component\Console\Terminal;
use Symfony\Component\Tui\Terminal\TerminalInterface;

/**
 * A terminal that only writes to the output of Castor.
 *
 * Unlike the default terminal of symfony/tui, it does not switch the terminal
 * to raw mode and does not read the input: the jobs do not need any input, and
 * Ctrl+C must still interrupt Castor and the processes it started.
 *
 * Its size is read once, and resizing the terminal is not followed: on a new
 * width, symfony/tui clears the screen and the scrollback, which would erase
 * what was printed before the jobs.
 *
 * @internal
 */
final class OutputTerminal implements TerminalInterface
{
    // The cursor is never hidden: Castor can be killed (Ctrl+C) before it gets
    // a chance to show it again. Its shape is never changed, so it is not reset
    // either: that would drop the one set by the shell (like in vi mode).
    private const CURSOR_SEQUENCES = ["\x1b[?25l", "\x1b[0 q"];

    private readonly Terminal $terminal;

    /**
     * @param resource $stream
     */
    public function __construct(
        private readonly mixed $stream,
    ) {
        $this->terminal = new Terminal();
    }

    public function start(callable $onInput, callable $onResize, callable $onKittyProtocolActivated): void
    {
    }

    public function stop(): void
    {
    }

    public function write(string $data): void
    {
        // symfony/tui writes them directly, without calling hideCursor()
        fwrite($this->stream, str_replace(self::CURSOR_SEQUENCES, '', $data));
        fflush($this->stream);
    }

    public function getColumns(): int
    {
        return $this->terminal->getWidth();
    }

    public function getRows(): int
    {
        return $this->terminal->getHeight();
    }

    public function isKittyProtocolActive(): bool
    {
        return false;
    }

    public function moveBy(int $lines): void
    {
        if ($lines > 0) {
            $this->write("\x1b[{$lines}B");
        } elseif ($lines < 0) {
            $this->write("\x1b[" . -$lines . 'A');
        }
    }

    public function hideCursor(): void
    {
    }

    public function showCursor(): void
    {
    }

    public function clearLine(): void
    {
        $this->write("\x1b[2K");
    }

    public function clearFromCursor(): void
    {
        $this->write("\x1b[0J");
    }

    public function clearScreen(): void
    {
        $this->write("\x1b[2J\x1b[H");
    }

    public function setTitle(string $title): void
    {
    }

    public function bell(): void
    {
    }

    public function isVirtual(): bool
    {
        return false;
    }
}
