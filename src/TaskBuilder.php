<?php

namespace Castor;

use Castor\Attribute\Task;
use Symfony\Component\Console\Command\Command;

class TaskBuilder
{
    public function __construct(private Task $taskAttribute, private \ReflectionFunction $function, private ContextRegistry $contextRegistry)
    {
    }

    public function getCommand(): Command
    {
        return new TaskAsCommand($this->taskAttribute, $this->function, $this->contextRegistry);
    }
}