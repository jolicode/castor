<?php

use Castor\Attribute\AsTask;

#[AsTask(description: 'hello')]
function hello(): void
{
    echo "Hello world from other castor file!\n";
}
