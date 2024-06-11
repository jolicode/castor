<?php

namespace Castor\Event;

use Castor\Context;

class ContextCreatedEvent
{
    public function __construct(public readonly string $contextName, public Context $context)
    {
    }
}
