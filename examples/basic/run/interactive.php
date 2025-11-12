<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function interactive(): void
{
    run('vim', context()->toInteractive());
}
