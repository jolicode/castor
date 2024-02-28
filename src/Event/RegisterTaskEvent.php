<?php

namespace Castor\Event;

use Symfony\Component\Console\Command\Command;

class RegisterTaskEvent
{
    public function __construct(
        public readonly Command $task,
        public bool $register = true,
    ) {
    }
}
