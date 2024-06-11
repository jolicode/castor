<?php

namespace cd;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Changes directory')]
function directory(): void
{
    run(['pwd']);
    run(['pwd'], context: context()->withWorkingDirectory('src/Attribute'));
    run(['pwd']);
}
