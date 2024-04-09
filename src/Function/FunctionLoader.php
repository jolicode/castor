<?php

namespace Castor\Function;

use Castor\Console\Application;
use Castor\Console\Command\SymfonyTaskCommand;
use Castor\ContextRegistry;
use Castor\Descriptor\ContextDescriptor;
use Castor\Descriptor\ContextGeneratorDescriptor;
use Castor\Descriptor\ListenerDescriptor;
use Castor\Descriptor\SymfonyTaskDescriptor;
use Castor\Descriptor\TaskDescriptor;
use Castor\Factory\TaskCommandFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** @internal */
final class FunctionLoader
{
    public function __construct(
        private readonly ContextRegistry $contextRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(lazy: true)]
        private readonly Application $application,
        private readonly TaskCommandFactory $taskCommandFactory,
    ) {
    }

    /**
     * @param list<ContextDescriptor>          $contextDescriptors
     * @param list<ContextGeneratorDescriptor> $contextGeneratorDescriptors
     */
    public function loadContexts(array $contextDescriptors, array $contextGeneratorDescriptors): void
    {
        foreach ($contextDescriptors as $descriptor) {
            $this->contextRegistry->addDescriptor($descriptor);
        }
        foreach ($contextGeneratorDescriptors as $descriptor) {
            foreach ($descriptor->generators as $name => $generator) {
                $this->contextRegistry->addContext($name, $generator);
            }
        }
    }

    /**
     * @param list<ListenerDescriptor> $listenerDescriptors
     */
    public function loadListeners(array $listenerDescriptors): void
    {
        foreach ($listenerDescriptors as $descriptor) {
            $this->eventDispatcher->addListener(
                $descriptor->asListener->event,
                // @phpstan-ignore-next-line
                $descriptor->reflectionFunction->getClosure(),
                $descriptor->asListener->priority
            );
        }
    }

    /**
     * @param list<TaskDescriptor>        $taskDescriptors
     * @param list<SymfonyTaskDescriptor> $symfonyTaskDescriptors
     */
    public function loadTasks(
        array $taskDescriptors,
        array $symfonyTaskDescriptors,
    ): void {
        foreach ($taskDescriptors as $descriptor) {
            $this->application->add($this->taskCommandFactory->createTask($descriptor));
        }
        foreach ($symfonyTaskDescriptors as $descriptor) {
            $this->application->add(SymfonyTaskCommand::createFromDescriptor($descriptor));
        }
    }
}
