<?php

use Castor\Attribute\Task;

#[Task(description: 'hello')]
function hello(): void
{
    echo 'Hello world!';
}
