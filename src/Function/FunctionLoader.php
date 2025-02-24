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
use Castor\Exception\FunctionConfigurationException;
use Castor\Factory\TaskCommandFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** @internal */
final readonly class FunctionLoader
{
    public function __construct(
        private ContextRegistry $contextRegistry,
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(lazy: true)]
        private Application $application,
        private TaskCommandFactory $taskCommandFactory,
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
        $previousDefault = null;

        foreach ($taskDescriptors as $descriptor) {
            $this->application->add($this->taskCommandFactory->createTask($descriptor));

            if ($descriptor->taskAttribute->default) {
                $taskName = $descriptor->taskAttribute->name;

                if ($descriptor->taskAttribute->namespace) {
                    $taskName = $descriptor->taskAttribute->namespace . ':' . $taskName;
                }

                if ($previousDefault) {
                    throw new FunctionConfigurationException(\sprintf('The task is marked as default, but task "%s()" was already marked as default.', $previousDefault), $descriptor->function);
                }

                $previousDefault = $taskName;
                $this->application->setDefaultCommand($taskName);
            }
        }
        foreach ($symfonyTaskDescriptors as $descriptor) {
            $this->application->add(SymfonyTaskCommand::createFromDescriptor($descriptor));
        }
    }
}
