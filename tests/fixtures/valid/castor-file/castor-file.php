<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

#[AsTask(description: 'hello')]
function hello(): void
{
    echo "Hello world from other castor file!\n";
}
