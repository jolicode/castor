<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;

use function Castor\context;

#[AsTask(description: 'Display the paths resolved from the root directory')]
function root(): void
{
    echo 'Working directory: ' . context()->workingDirectory . "\n";
    echo 'Vendor directory: ' . PathHelper::getCastorVendorDir() . "\n";
}
