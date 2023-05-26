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
    private ?ContextBuilder $defaultContextBuilder = null;

    public function addContextBuilder(ContextBuilder $contextBuilder): void
    {
        if (\array_key_exists($contextBuilder->getName(), $this->contextBuilders)) {
            throw new \RuntimeException(sprintf('Context "%s" already exists.', $contextBuilder->getName()));
        }

        $this->contextBuilders[$contextBuilder->getName()] = $contextBuilder;

        if ($contextBuilder->isDefault()) {
            if ($this->defaultContextBuilder) {
                throw new \RuntimeException(sprintf('Default context already set to "%s".', $this->defaultContextBuilder->getName()));
            }
            $this->defaultContextBuilder = $contextBuilder;
        }
    }

    public function setDefaultContextIfEmpty(): void
    {
        if (!$this->defaultContextBuilder) {
            if (1 === \count($this->contextBuilders)) {
                $this->defaultContextBuilder = reset($this->contextBuilders);

                return;
            }

            throw new \RuntimeException(sprintf('Since there are multiple contexts "%s", you must set a default context.', implode('", "', array_keys($this->contextBuilders))));
        }
    }

    public function getDefaultContextBuilder(): ContextBuilder
    {
        return $this->defaultContextBuilder ?? throw new \RuntimeException('Default context not set yet.');
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
