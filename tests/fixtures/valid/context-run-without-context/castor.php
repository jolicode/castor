<?php

use Castor\Attribute\AsContext;

use function Castor\run;

#[AsContext(default: true)]
function context(): void
{
    run(['pwd']);
}
