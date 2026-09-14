<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\parallel;
use function Castor\run;

#[AsTask()]
function parallel_timeout(): void
{
    parallel(
        static fn () => run('sleep 30', context: context()->withTimeout(1)),
    );
}
