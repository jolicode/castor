<?php

namespace open;

use Castor\Attribute\AsTask;

use function Castor\open;

#[AsTask(description: 'Open Castor documentation in the default browser')]
function documentation(): void
{
    open('https://castor.jolicode.com');
}

#[AsTask(description: 'Open an URL and a file in the default applications')]
function multiple(): void
{
    open('https://castor.jolicode.com', 'examples/open.php');
}
