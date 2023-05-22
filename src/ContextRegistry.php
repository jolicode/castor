<?php

namespace Castor;

class ContextRegistry
{
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
}
