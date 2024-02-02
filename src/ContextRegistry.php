<?php

namespace Castor;

/** @internal */
class ContextRegistry
{
    /** @var array<string, ContextDescriptor> */
    private array $descriptors = [];
    private ?string $defaultName = null;

    private Context $currentContext;

    public function addDescriptor(ContextDescriptor $descriptor): void
    {
        $name = $descriptor->contextAttribute->name;
        if (\array_key_exists($name, $this->descriptors)) {
            throw new \RuntimeException(sprintf('You cannot defined two context with the same name "%s". There is one defined in "%s" and another in "%s".', $name, $this->describeFunction($this->descriptors[$name]->function), $this->describeFunction($descriptor->function)));
        }

        $this->descriptors[$name] = $descriptor;

        if ($descriptor->contextAttribute->default) {
            if ($this->defaultName) {
                throw new \RuntimeException(sprintf('You cannot set multiple "default: true" context. There is one defined in "%s" and another in "%s".', $this->defaultName, $this->describeFunction($descriptor->function)));
            }
            $this->defaultName = $name;
        }
    }

    public function addContext(string $name, \Closure $callable, bool $default = false): void
    {
        $this->addDescriptor(new ContextDescriptor(
            new Attribute\AsContext(name: $name, default: $default),
            new \ReflectionFunction($callable),
        ));
    }

    public function setDefaultIfEmpty(): void
    {
        if ($this->defaultName) {
            return;
        }

        if (!$this->descriptors) {
            return;
        }

        if (1 < \count($this->descriptors)) {
            throw new \RuntimeException(sprintf('Since there are multiple contexts "%s", you must set a "default: true" context.', implode('", "', $this->getNames())));
        }

        $this->defaultName = array_key_first($this->descriptors);
    }

    public function getDefaultName(): string
    {
        return $this->defaultName ?? throw new \LogicException('Default context name not set yet.');
    }

    public function get(?string $name = null): Context
    {
        if (null === $name) {
            return $this->getCurrentContext();
        }

        if (!\array_key_exists($name, $this->descriptors)) {
            throw new \RuntimeException(sprintf('Context "%s" not found.', $name));
        }

        return $this->descriptors[$name]->function->invoke();
    }

    public function setCurrentContext(Context $context): void
    {
        $this->currentContext = $context;
    }

    public function getCurrentContext(): Context
    {
        if (isset($this->currentContext)) {
            return $this->currentContext;
        }

        trigger_deprecation('castor', '0.11.1', 'Calling getCurrentContext() without setCurrentContext() is deprecated. Pass a context instead to the function, or set a Current context before.');

        return new Context();
    }

    /**
     * @template TKey of key-of<ContextData>
     * @template TDefault
     *
     * @param TKey|string $key
     * @param TDefault    $default
     *
     * @phpstan-return ($key is TKey ? ContextData[TKey] : TDefault)
     */
    public function getVariable(string $key, mixed $default = null): mixed
    {
        $context = $this->getCurrentContext();

        if (!isset($context[$key])) {
            return $default;
        }

        return $context[$key];
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

        if (str_contains($name, '{closure}')) {
            return $location;
        }

        return sprintf('%s@%s', $name, $location);
    }
}
