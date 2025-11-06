<?php

namespace open;

use Castor\Attribute\AsTask;

use function Castor\open;

#[AsTask(description: 'Open Castor documentation in the default browser')]
function documentation(): void
{
    open('https://castor.jolicode.com');
}
