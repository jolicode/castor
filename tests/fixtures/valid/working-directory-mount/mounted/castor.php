<?php

// A mounted castor.php defining the constant too: the guard keeps it from raising
// "Constant CASTOR_USE_CHDIR already defined", since both files share one process.
\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

#[AsTask(name: 'mounted-cwd')]
function mounted_cwd(): void
{
    echo 'mounted: ' . basename((string) getcwd()) . "\n";
}
