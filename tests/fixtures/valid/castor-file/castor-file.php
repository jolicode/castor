<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;

use function Castor\context;

#[AsTask(description: 'hello')]
function hello(): void
{
    echo "Hello world from other castor file!\n";
}

#[AsTask(description: 'Display the paths resolved from the root directory')]
function root(): void
{
    echo 'Root: ' . PathHelper::getRoot() . "\n";
    echo 'Vendor directory: ' . PathHelper::getCastorVendorDir() . "\n";
    // The working directory must stay where castor was started from, and not follow
    // the "--castor-file" option
    echo 'Working directory: ' . context()->workingDirectory . "\n";
}
