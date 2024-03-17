<?php

namespace cd;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Changes directory')]
function directory(): void
{
    run(['pwd']);
    run(['pwd'], workingDirectory: 'src/Attribute');
    run(['pwd']);
}
