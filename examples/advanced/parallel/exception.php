<?php

namespace parallel;

use Castor\Attribute\AsTask;

use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Sleep and throw an exception')]
function exception(): void
{
    parallel(
        static fn () => run('exit 1'),
        static fn () => run('sleep 1; echo "I am executed"'),
        static fn () => throw new \RuntimeException('This is an exception'),
    );
}
