<?php

namespace Castor;

use Psr\Log\LoggerInterface;

/** @internal */
class ContextRegistry
{
    private static Context $initialContext;
    private static LoggerInterface $logger;

    /** @var array<string, ContextBuilder> */
    private array $contextBuilders = [];

    public function addContextBuilder(string $name, ContextBuilder $context): void
    {
        $this->contextBuilders[$name] = $context;
    }

    public function getContextBuilder(string $name): ContextBuilder
    {
        return $this->contextBuilders[$name] ?? throw new \RuntimeException(sprintf('Context "%s" not found.', $name));
    }

    /**
     * @return array<string>
     */
    public function getContextNames(): array
    {
        return array_keys($this->contextBuilders);
    }

    public static function setInitialContext(Context $initialContext): void
    {
        self::$initialContext = $initialContext;
    }

    public static function getInitialContext(): Context
    {
        return self::$initialContext ??= new Context();
    }

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function getLogger(): LoggerInterface
    {
        return self::$logger ?? throw new \RuntimeException('Logger not set yet.');
    }
}
