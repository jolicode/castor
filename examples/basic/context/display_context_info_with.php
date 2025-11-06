<?php

namespace context;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\io;
use function Castor\with;

#[AsTask(description: 'Displays information about the context, using a specific context')]
function display_context_info_with(): void
{
    $result = with(
        function (Context $context) {
            display_context_info();

            return $context->data['foo'] ?? 'N/A';
        },
        data: ['foo' => 'bar'],
        context: 'dynamic',
    );

    io()->writeln($result);
}
