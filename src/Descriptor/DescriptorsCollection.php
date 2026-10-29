<?php

namespace Castor\Descriptor;

final readonly class DescriptorsCollection
{
    /**
     * @param list<ContextDescriptor>          $contextDescriptors
     * @param list<ContextGeneratorDescriptor> $contextGeneratorDescriptors
     * @param list<ListenerDescriptor>         $listenerDescriptors
     * @param list<TaskDescriptor>             $taskDescriptors
     * @param list<SymfonyTaskDescriptor>      $symfonyTaskDescriptors
     */
    public function __construct(
        public array $contextDescriptors,
        public array $contextGeneratorDescriptors,
        public array $listenerDescriptors,
        public array $taskDescriptors,
        public array $symfonyTaskDescriptors,
    ) {
    }

    /**
     * The functions and the classes the descriptors are resolved from.
     *
     * @return list<\ReflectionFunction|\ReflectionClass<object>>
     */
    public function getDefinitions(): array
    {
        return [
            ...array_map(static fn (ContextDescriptor $descriptor): \ReflectionFunction => $descriptor->function, $this->contextDescriptors),
            ...array_map(static fn (ContextGeneratorDescriptor $descriptor): \ReflectionFunction => $descriptor->function, $this->contextGeneratorDescriptors),
            ...array_map(static fn (ListenerDescriptor $descriptor): \ReflectionFunction => $descriptor->reflectionFunction, $this->listenerDescriptors),
            ...array_map(static fn (TaskDescriptor $descriptor): \ReflectionFunction => $descriptor->function, $this->taskDescriptors),
            ...array_map(static fn (SymfonyTaskDescriptor $descriptor): \ReflectionClass => $descriptor->function, $this->symfonyTaskDescriptors),
        ];
    }
}
