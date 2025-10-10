<?php

namespace Castor;

use Castor\Attribute\AsContext;
use Castor\Descriptor\ContextDescriptor;
use Castor\Event\ContextCreatedEvent;
use Castor\Exception\FunctionConfigurationException;
use Castor\Helper\PathHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** @internal */
class ContextRegistry
{
    /** @var array<string, ContextDescriptor> */
    private array $descriptors = [];
    /** @var array<string, Context> */
    private array $contexts = [];

    private Context $currentContext;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire('%context%')]
        private ?string $defaultName = null,
    ) {
    }

    public function addDescriptor(ContextDescriptor $descriptor): void
    {
        $name = $descriptor->contextAttribute->name;
        if (\array_key_exists($name, $this->descriptors)) {
            $alreadyDefined = $this->descriptors[$name]->function;

            throw new FunctionConfigurationException(\sprintf('You cannot define two contexts with the same name "%s". There is one already defined in "%s:%d".', $name, PathHelper::makeRelative((string) $alreadyDefined->getFileName()), $alreadyDefined->getStartLine()), $descriptor->function);
        }

        $this->descriptors[$name] = $descriptor;

        if ($descriptor->contextAttribute->default) {
            if ($this->defaultName && ($this->descriptors[$this->defaultName] ?? false)) {
                $alreadyDefined = $this->descriptors[$this->defaultName]->function;

                throw new FunctionConfigurationException(\sprintf('You cannot set multiple "default: true" context. There is one already defined in "%s:%d".', PathHelper::makeRelative((string) $alreadyDefined->getFileName()), $alreadyDefined->getStartLine()), $descriptor->function);
            }
            $this->defaultName = $name;
        }
    }

    /**
     * @param \Closure(): Context $callable
     */
    public function addContext(string $name, callable $callable, bool $default = false): void
    {
        $this->addDescriptor(new ContextDescriptor(
            new AsContext(name: $name, default: $default),
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
            throw new \RuntimeException(\sprintf('Since there are multiple contexts "%s", you must set a "default: true" context.', implode('", "', $this->getNames())));
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

        if (isset($this->contexts[$name])) {
            return $this->contexts[$name];
        }

        if (!\array_key_exists($name, $this->descriptors)) {
            throw new \RuntimeException(\sprintf('Context "%s" not found.', $name));
        }

        $context = $this->descriptors[$name]->function->invoke();
        if (!$context instanceof Context) {
            throw new FunctionConfigurationException(\sprintf('The context generator must return an instance of "%s", "%s" returned.', Context::class, get_debug_type($context)), $this->descriptors[$name]->function);
        }

        $context = $context->withName($name);

        $event = new ContextCreatedEvent($name, $context);
        $this->eventDispatcher->dispatch($event);

        $this->contexts[$name] = $event->context;

        return $this->contexts[$name];
    }

    public function hasCurrentContext(): bool
    {
        return isset($this->currentContext);
    }

    public function setCurrentContext(Context $context): void
    {
        $this->currentContext = $context;
    }

    public function getCurrentContext(): Context
    {
        if (!isset($this->currentContext)) {
            throw new \RuntimeException('No current context defined. You can set one with ContextRegistry::setCurrentContext() or get the default one with ContextRegistry::get().');
        }

        return $this->currentContext;
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
}
