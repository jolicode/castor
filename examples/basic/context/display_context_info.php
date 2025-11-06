<?php

namespace context;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\variable;

#[AsTask(description: 'Displays information about the context', name: 'context', aliases: ['context-info'])]
function display_context_info(bool $test = false): void
{
    $context = context();

    io()->writeln('context name: ' . variable('name', 'N/A'));
    io()->writeln('Production? ' . (variable('production', false) ? 'yes' : 'no'));
    io()->writeln("verbosity: {$context->verbosityLevel->value}");
    io()->writeln('context: ' . variable('foo', 'N/A'));
    io()->writeln('nested merge recursive: ' . json_encode(variable('nested', []), \JSON_THROW_ON_ERROR));
}
