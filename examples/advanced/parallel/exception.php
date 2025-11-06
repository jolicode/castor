<?php

namespace parallel;

use Castor\Attribute\AsTask;

use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Sleep and throw an exception')]
function exception(): void
{
    parallel(
        fn () => run('exit 1'),
        fn () => run('sleep 1; echo "I am executed"'),
        fn () => throw new \RuntimeException('This is an exception'),
    );
}
