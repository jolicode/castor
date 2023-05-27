<?php

namespace Castor;

use Psr\Log\LoggerInterface;

class GlobalHelper
{
    private static Context $initialContext;
    private static LoggerInterface $logger;

    public static function setInitialContext(Context $initialContext): void
    {
        self::$initialContext = $initialContext;
    }

    public static function getInitialContext(): Context
    {
        // We always need a default context, for example when using exec() in a context builder
        return self::$initialContext ?? new Context();
    }

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function getLogger(): LoggerInterface
    {
        return self::$logger ?? throw new \LogicException('Logger not set yet.');
    }
}
