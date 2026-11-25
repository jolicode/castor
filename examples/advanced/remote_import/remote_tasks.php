<?php

namespace remote_import;

use Castor\Attribute\AsTask;

use function Castor\register_plugin;
use function Castor\Tests\autoload\loadme;
use function castor_example\greet;

// Exposing the tasks of a plugin required in castor.composer.json: the ones of
// the files it autoloads (its "default" feature), and the ones of its "foobar"
// feature
register_plugin('castor-example/plugin', ['default', 'foobar']);

#[AsTask(description: 'Use functions of a remote package')]
function remote_tasks(): void
{
    echo greet('remote_import') . \PHP_EOL;
}

#[AsTask(description: 'Can invoke autoloaded files from castor.composer.json')]
function autoload(): void
{
    loadme();
}
