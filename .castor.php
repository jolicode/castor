<?php

use Castor\Attribute\Task;

use function Castor\import;

import(__DIR__ . '/watcher/.castor');

#[Task(description: 'hello')]
function hello(): void
{
    echo 'Hello world!';
}
