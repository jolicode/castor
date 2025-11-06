<?php

namespace context;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;

#[AsTask(description: 'Displays information about the context')]
function display_dynamic_context_info(): void
{
    $context = context('dynamic');

    io()->writeln('context name: ' . $context->data['name']);
}
