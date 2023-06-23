<?php

namespace Castor;

/** @internal */
class ContextRegistry
{
    /** @var array<string, ContextDescriptor> */
    private array $descriptors = [];
    private ?string $default = null;

    public function add(ContextDescriptor $descriptor): void
    {
        $name = $descriptor->contextAttribute->name;
        if (\array_key_exists($name, $this->descriptors)) {
            throw new \RuntimeException(sprintf('You cannot defined two context with the same name "%s". There is one defined in "%s" and another in "%s".', $name, $this->describeFunction($this->descriptors[$name]->function), $this->describeFunction($descriptor->function)));
        }

        $this->descriptors[$name] = $descriptor;

        if ($descriptor->contextAttribute->default) {
            if ($this->default) {
                throw new \RuntimeException(sprintf('You cannot set multiple "default: true" context. There is one defined in "%s" and another in "%s".', $this->default, $this->describeFunction($descriptor->function)));
            }
            $this->default = $name;
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
            throw new \RuntimeException(sprintf('Since there are multiple contexts "%s", you must set a "default: true" context.', implode('", "', $this->getNames())));
        }

        $this->default = array_key_first($this->descriptors);
    }

    public function getDefault(): string
    {
        return $this->default ?? throw new \LogicException('Default context not set yet.');
    }

    public function get(string $name): Context
    {
        if (!\array_key_exists($name, $this->descriptors)) {
            throw new \RuntimeException(sprintf('Context "%s" not found.', $name));
        }

        return $this->descriptors[$name]->function->invoke();
    }

    /**
     * @return array<string>
     */
    public function getNames(): array
    {
        $names = array_keys($this->descriptors);
        sort($names);

        return $names;
    }

    private function describeFunction(\ReflectionFunction $function): string
    {
        $name = $function->getName();
        $shortFilename = str_replace(PathHelper::getRoot() . '/', '', (string) $function->getFileName());
        $location = sprintf('%s:%d', $shortFilename, $function->getStartLine());

        return sprintf('%s@%s', $name, $location);
    }
}
