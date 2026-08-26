<?php

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\context;
use function Castor\fs;
use function Castor\with;

#[AsTask]
function cwd(): void
{
    echo 'cwd: ' . basename((string) getcwd()) . "\n";
    echo 'context: ' . basename(context()->workingDirectory) . "\n";
    echo 'run: ' . basename(capture('pwd')) . "\n";
    echo 'fs: ' . (fs()->exists('castor.php') ? 'found castor.php' : 'castor.php not found') . "\n";
}

#[AsTask(name: 'cwd-with')]
function cwd_with(): void
{
    with(static function () {
        echo 'inside: ' . basename((string) getcwd()) . "\n";
        echo 'inside run: ' . basename(capture('pwd')) . "\n";
    }, workingDirectory: 'sub');

    echo 'outside: ' . basename((string) getcwd()) . "\n";
}
