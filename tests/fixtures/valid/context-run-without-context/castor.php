<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsContext;

use function Castor\run;

#[AsContext(default: true)]
function context(): void
{
    run(['pwd']);
}
