<?php

namespace Castor\Function;

use Castor\ContextRegistry;
use Castor\Descriptor\ContextDescriptor;
use Castor\Descriptor\ContextGeneratorDescriptor;
use Castor\Descriptor\ListenerDescriptor;
use Castor\Descriptor\SymfonyTaskDescriptor;
use Castor\Descriptor\TaskDescriptor;
use Castor\Descriptor\TaskDescriptorCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** @internal */
final class FunctionLoader
{
    public function __construct(
        private readonly FunctionFinder $functionFinder,
        private readonly ContextRegistry $contextRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param list<string>       $previousFunctions
     * @param list<class-string> $previousClasses
     */
    public function load(array $previousFunctions, array $previousClasses): TaskDescriptorCollection
    {
        $descriptors = $this
            ->functionFinder
            ->findFunctions($previousFunctions, $previousClasses)
        ;

        return $this->loadFunctions($descriptors);
    }

    /**
     * @param iterable<TaskDescriptor|ContextDescriptor|ContextGeneratorDescriptor|ListenerDescriptor|SymfonyTaskDescriptor> $descriptors
     */
    private function loadFunctions(iterable $descriptors): TaskDescriptorCollection
    {
        $tasks = [];
        $symfonyTasks = [];
        foreach ($descriptors as $descriptor) {
            if ($descriptor instanceof TaskDescriptor) {
                $tasks[] = $descriptor;
            } elseif ($descriptor instanceof SymfonyTaskDescriptor) {
                $symfonyTasks[] = $descriptor;
            } elseif ($descriptor instanceof ContextDescriptor) {
                $this->contextRegistry->addDescriptor($descriptor);
            } elseif ($descriptor instanceof ContextGeneratorDescriptor) {
                foreach ($descriptor->generators as $name => $generator) {
                    $this->contextRegistry->addContext($name, $generator);
                }
            } elseif ($descriptor instanceof ListenerDescriptor && null !== $descriptor->reflectionFunction->getClosure()) {
                $this->eventDispatcher->addListener(
                    $descriptor->asListener->event,
                    $descriptor->reflectionFunction->getClosure(),
                    $descriptor->asListener->priority
                );
            }
        }

        return new TaskDescriptorCollection($tasks, $symfonyTasks);
    }
}
