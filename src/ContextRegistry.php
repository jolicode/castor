<?php

namespace Castor;

class ContextRegistry
{
    private static Context $currentContext;

    /** @var array<string, ContextBuilder> */
    private array $contexts = [];

    public function addContext(string $name, ContextBuilder $context): void
    {
        $this->contexts[$name] = $context;
    }

    public function getContext(string $name): ContextBuilder
    {
        return $this->contexts[$name] ?? throw new \RuntimeException(sprintf('Context "%s" not found.', $name));
    }

    /**
     * @return array<string>
     */
    public function getContextNames(): array
    {
        return array_keys($this->contexts);
    }

    public static function setCurrentContext(Context $context): void
    {
        self::$currentContext = $context;
    }

    public static function getCurrentContext(): Context
    {
        return self::$currentContext ??= new Context();
    }
}
