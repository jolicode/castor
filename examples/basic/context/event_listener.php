<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsListener;
use Castor\Context;
use Castor\Event\ContextCreatedEvent;

#[AsContext(name: 'updated')]
function create_updated_context(): Context
{
    return new Context();
}

#[AsListener(ContextCreatedEvent::class)]
function update_context(ContextCreatedEvent $event): void
{
    if ('updated' !== $event->contextName) {
        return;
    }

    $context = $event->context;
    $event->context = $context->withData(['name' => 'updated_context']);
}
