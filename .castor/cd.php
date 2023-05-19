<?php

namespace cd;

use Castor\Attribute\Task;

use function Castor\cd;
use function Castor\exec;

#[Task(description: 'A simple command that changes directory')]
function directory()
{
    exec(['pwd']);
    cd('../');
    exec(['pwd']);
}
