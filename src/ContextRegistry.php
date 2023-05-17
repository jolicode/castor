<?php

namespace Castor;

class ContextRegistry
{
    private array $contexts = [];

    public function addContext(string $name, ContextBuilder $context): void
    {
        $this->contexts[$name] = $context;
    }

    public function getContext(string $name): ContextBuilder
    {
        return $this->contexts[$name];
    }

    public function getContextsName(): array
    {
        return array_keys($this->contexts);
    }
}
