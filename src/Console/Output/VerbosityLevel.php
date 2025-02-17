<?php

namespace Castor\Console\Output;

use Symfony\Component\Console\Output\OutputInterface;

enum VerbosityLevel: int
{
    case NOT_CONFIGURED = -10;
    case SILENT = -1;
    case QUIET = 0;
    case NORMAL = 1;
    case VERBOSE = 2;
    case VERY_VERBOSE = 3;
    case DEBUG = 4;

    public static function fromSymfonyOutput(OutputInterface $output): self
    {
        return match ($output->getVerbosity()) {
            OutputInterface::VERBOSITY_SILENT => self::SILENT,
            OutputInterface::VERBOSITY_QUIET => self::QUIET,
            OutputInterface::VERBOSITY_NORMAL => self::NORMAL,
            OutputInterface::VERBOSITY_VERBOSE => self::VERBOSE,
            OutputInterface::VERBOSITY_VERY_VERBOSE => self::VERY_VERBOSE,
            OutputInterface::VERBOSITY_DEBUG => self::DEBUG,
        };
    }

    public function isNotConfigured(): bool
    {
        return self::NOT_CONFIGURED === $this;
    }

    public function isQuiet(): bool
    {
        return self::QUIET->value === $this->value;
    }

    public function isVerbose(): bool
    {
        return self::VERBOSE->value <= $this->value;
    }

    public function isVeryVerbose(): bool
    {
        return self::VERY_VERBOSE->value <= $this->value;
    }

    public function isDebug(): bool
    {
        return self::DEBUG->value <= $this->value;
    }
}
