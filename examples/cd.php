<?php

namespace cd;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Changes directory')]
function directory()
{
    run(['pwd']);
    run(['pwd'], path: 'src/Attribute');
    run(['pwd']);
}
