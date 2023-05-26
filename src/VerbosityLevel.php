<?php

namespace Castor;

use Symfony\Component\Console\Output\OutputInterface;

enum VerbosityLevel: int
{
    case QUIET = 0;
    case NORMAL = 1;
    case VERBOSE = 2;
    case VERY_VERBOSE = 3;
    case DEBUG = 4;

    public static function fromSymfonyOutput(OutputInterface $output): self
    {
        return match ($output->getVerbosity()) {
            OutputInterface::VERBOSITY_QUIET => self::QUIET,
            OutputInterface::VERBOSITY_NORMAL => self::NORMAL,
            OutputInterface::VERBOSITY_VERBOSE => self::VERBOSE,
            OutputInterface::VERBOSITY_VERY_VERBOSE => self::VERY_VERBOSE,
            OutputInterface::VERBOSITY_DEBUG => self::DEBUG,
        };
    }
}
