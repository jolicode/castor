<?php

namespace Castor\Runner\Parallel;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes to the error output of the job the code currently running belongs
 * to, if any, and to the given output otherwise.
 *
 * Used for the logs: written straight to the console, they would break the
 * display of the jobs.
 *
 * @internal
 */
final readonly class JobAwareOutput implements OutputInterface
{
    public function __construct(
        private JobRegistry $jobRegistry,
        private OutputInterface $output,
    ) {
    }

    /**
     * @param string|iterable<string> $messages
     */
    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->getOutput()->write($messages, $newline, $options);
    }

    /**
     * @param string|iterable<string> $messages
     */
    public function writeln(string|iterable $messages, int $options = 0): void
    {
        $this->getOutput()->writeln($messages, $options);
    }

    public function setVerbosity(int $level): void
    {
        $this->output->setVerbosity($level);
    }

    public function getVerbosity(): int
    {
        return $this->getOutput()->getVerbosity();
    }

    public function isSilent(): bool
    {
        return $this->getOutput()->isSilent();
    }

    public function isQuiet(): bool
    {
        return $this->getOutput()->isQuiet();
    }

    public function isVerbose(): bool
    {
        return $this->getOutput()->isVerbose();
    }

    public function isVeryVerbose(): bool
    {
        return $this->getOutput()->isVeryVerbose();
    }

    public function isDebug(): bool
    {
        return $this->getOutput()->isDebug();
    }

    public function setDecorated(bool $decorated): void
    {
        $this->output->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->getOutput()->isDecorated();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->output->setFormatter($formatter);
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return $this->getOutput()->getFormatter();
    }

    private function getOutput(): OutputInterface
    {
        return $this->jobRegistry->getCurrent()->errorOutput ?? $this->output;
    }
}
