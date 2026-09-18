<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\run_php;

#[AsTask]
function restart(): void
{
    run_php(__DIR__ . '/tool/restart.php', ['analyze', 'src']);
}
