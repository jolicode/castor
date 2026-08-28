<?php

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\context;
use function Castor\fs;

#[AsTask]
function cwd(): void
{
    echo 'cwd: ' . basename((string) getcwd()) . "\n";
    echo 'context: ' . basename(context()->workingDirectory) . "\n";
    echo 'run: ' . basename(capture('pwd')) . "\n";
    echo 'fs: ' . (fs()->exists('castor.php') ? 'found castor.php' : 'castor.php not found') . "\n";
}
