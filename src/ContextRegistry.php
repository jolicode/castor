<?php

namespace Castor;

/** @internal */
class ContextRegistry
{
    /** @var array<string, ContextDescriptor> */
    private array $descriptors = [];
    private ?ContextDescriptor $default = null;

    public function add(ContextDescriptor $descriptor): void
    {
        $name = $descriptor->contextAttribute->name;
        if (\array_key_exists($name, $this->descriptors)) {
            throw new \RuntimeException(sprintf('You cannot defined two context with the same name "%s". There is one defined in "%s" and another in "%s".', $name, $this->descriptors[$name]->function->getName(), $descriptor->function->getName()));
        }

        $this->descriptors[$name] = $descriptor;

        if ($descriptor->contextAttribute->default) {
            if ($this->default) {
                throw new \RuntimeException(sprintf('You cannot set multiple "default: true" context. There is one defined in "%s" and another in "%s".', $this->default->function->getName(), $descriptor->function->getName()));
            }
            $this->default = $descriptor;
        }
    }

    public function setDefaultIfEmpty(): void
    {
        if ($this->default) {
            return;
        }

        if (!$this->descriptors) {
            return;
        }

        if (1 < \count($this->descriptors)) {
            throw new \RuntimeException(sprintf('Since there are multiple contexts "%s", you must set a "default: true" context.', implode('", "', array_keys($this->descriptors))));
        }

        $this->default = reset($this->descriptors);
    }

    public function getDefault(): ContextDescriptor
    {
        return $this->default ?? throw new \LogicException('Default descriptor not set yet.');
    }

    public function get(string $name): ContextDescriptor
    {
        return $this->descriptors[$name] ?? throw new \RuntimeException(sprintf('Descriptor "%s" not found.', $name));
    }

    /**
     * @return array<string>
     */
    public function getNames(): array
    {
        return array_keys($this->descriptors);
    }
}
