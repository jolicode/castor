<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Changes directory')]
function working_directory_override(): void
{
    run(['pwd']);
    run(['pwd'], context()->withWorkingDirectory('src/Attribute'));
    run(['pwd']);
}
