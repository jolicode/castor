<?php

namespace castor_example;

use Castor\Attribute\AsTask;

use function Castor\io;

/**
 * Not autoloaded: only exposed when the "foobar" feature is asked for.
 */
#[AsTask(namespace: 'castor-example', description: 'Foobar from the example plugin!')]
function foobar(): void
{
    io()->title('This is foobar, from the example plugin!');
}
