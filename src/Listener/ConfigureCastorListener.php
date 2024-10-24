<?php

namespace Castor\Listener;

use Castor\Event\BeforeBootEvent;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** @internal */
class ConfigureCastorListener
{
    public function __construct(
        private readonly ErrorHandler $errorHandler,
        private readonly OutputInterface $output,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    #[AsEventListener()]
    public function configureCastor(BeforeBootEvent $event): void
    {
        if ($this->logger instanceof Logger) {
            $this->logger->pushHandler(new ConsoleHandler($this->output));
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

        if (\PHP_VERSION_ID < 80400) {
            $map[\E_STRICT] = LogLevel::ERROR;
        }

        $this->errorHandler->setDefaultLogger($this->logger, $map);
    }
}
