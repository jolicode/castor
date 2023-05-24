<?php

use Castor\Attribute\AsTask;

use function Castor\import;

import(__DIR__ . '/watcher/.castor');

#[AsTask(description: 'hello')]
function hello(): void
{
    echo 'Hello world!';
}
