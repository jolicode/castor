<?php

namespace castor_example;

use Castor\Attribute\AsTask;

use function Castor\io;

/**
 * A function of the plugin: available as soon as the plugin is required.
 */
function greet(string $name): string
{
    return "Hello {$name}, from the example plugin!";
}

/**
 * Autoloaded by the plugin, so exposed by its "default" feature.
 */
#[AsTask(namespace: 'castor-example', description: 'Hello from the example plugin!')]
function hello(): void
{
    io()->title(greet('Castor'));
}
