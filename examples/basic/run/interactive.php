<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\run;
use function Castor\context;

#[AsTask()]
function interactive(): void
{
    run('vim', context()->toInteractive());
}
