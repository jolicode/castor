<?php

namespace import;

use Castor\Attribute\AsTask;

use function Castor\import;

import('github://pyrech/castor-setup-php/castor.php@main');

#[AsTask(description: 'Use a function imported from a remote repository')]
function hello(): void
{
    \pyrech\helloWorld();
}
