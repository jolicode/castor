<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\context;
use function Castor\parallel;
use function Castor\with;

#[AsTask(name: 'par')]
function par(): void
{
    parallel(
        static fn () => with(static function () {
            capture('sleep 0.1');
            echo 'a after resume: context=' . basename(context()->workingDirectory) . ' run=' . basename(capture('pwd')) . "\n";
        }, workingDirectory: 'a'),
        static fn () => with(static function () {
            capture('sleep 0.3');
            echo 'b after resume: context=' . basename(context()->workingDirectory) . "\n";
        }, workingDirectory: 'b'),
    );

    echo 'after parallel: context=' . basename(context()->workingDirectory) . "\n";
}
