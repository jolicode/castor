<?php

namespace Castor\Factory;

use Castor\Console\Command\TaskCommand;
use Castor\ContextRegistry;
use Castor\Descriptor\TaskDescriptor;
use Castor\ExpressionLanguage;
use Castor\Helper\Slugger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

class TaskCommandFactory
{
    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ContextRegistry $contextRegistry,
        private readonly Slugger $slugger,
        private readonly Filesystem $fs,
    ) {
    }

    public function createTask(TaskDescriptor $taskDescriptor): TaskCommand
    {
        return new TaskCommand(
            $taskDescriptor,
            $this->expressionLanguage,
            $this->eventDispatcher,
            $this->contextRegistry,
            $this->slugger,
            $this->fs,
        );
    }
}
