<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\mount;

mount(__DIR__ . '/mounted');

#[AsTask]
function cwd(): void
{
    echo 'root: ' . basename((string) getcwd()) . "\n";
}
