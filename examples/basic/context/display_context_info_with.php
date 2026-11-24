<?php

namespace context;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\context;
use function Castor\io;
use function Castor\with;

#[AsTask(description: 'Displays information about the context, using a specific context')]
function display_context_info_with(): void
{
    $result = with(
        static function (Context $context) {
            display_context_info();

            return $context->data['foo'] ?? 'N/A';
        },
        context: context('dynamic', data: ['foo' => 'bar']),
    );

    io()->writeln($result);
}
