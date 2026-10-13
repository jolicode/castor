<?php

namespace Castor\Listener;

use Castor\Event\BeforeBootEvent;
use Castor\Runner\Parallel\JobAwareOutput;
use Castor\Runner\Parallel\JobRegistry;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** @internal */
class ConfigureCastorListener
{
    public function __construct(
        private readonly ErrorHandler $errorHandler,
        private readonly JobRegistry $jobRegistry,
        private readonly OutputInterface $output,
        #[Autowire('%use_output_section%')]
        private readonly bool $useOutputSection,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    #[AsEventListener()]
    public function configureCastor(BeforeBootEvent $event): void
    {
        if ($this->logger instanceof Logger) {
            // Inside a job of parallel(), the logs are organized with its output
            $this->logger->pushHandler(new ConsoleHandler(new JobAwareOutput($this->jobRegistry, $this->output)));
        }

        $map = [
            \E_COMPILE_WARNING => LogLevel::WARNING,
            \E_CORE_WARNING => LogLevel::WARNING,
            \E_USER_WARNING => LogLevel::WARNING,
            \E_WARNING => LogLevel::WARNING,
            \E_USER_DEPRECATED => LogLevel::WARNING,
            \E_DEPRECATED => LogLevel::WARNING,
            \E_USER_NOTICE => LogLevel::WARNING,
            \E_NOTICE => LogLevel::WARNING,

            \E_COMPILE_ERROR => LogLevel::ERROR,
            \E_CORE_ERROR => LogLevel::ERROR,
            \E_ERROR => LogLevel::ERROR,
            \E_PARSE => LogLevel::ERROR,
            \E_RECOVERABLE_ERROR => LogLevel::ERROR,
            \E_USER_ERROR => LogLevel::ERROR,
        ];

        $this->errorHandler->setDefaultLogger($this->logger, $map);

        // Once the logger is configured, to be reported
        if ($this->useOutputSection) {
            trigger_deprecation('jolicode/castor', '1.9.0', 'The "CASTOR_USE_SECTION" environment variable is deprecated, name the functions given to parallel() to organize their output instead.');
        }
    }
}
