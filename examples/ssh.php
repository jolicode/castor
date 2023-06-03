<?php

namespace ssh;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\run;

#[AsTask(description: 'Runs a command on a remote server')]
function ls(Context $context): void
{
    $context = $context->withSsh('holonet.loickpiera.com', 'anakin', [
        'port' => 2222,
    ])->withEnvironment([
        'TOTO' => 'foo',
    ]);

    run('ls -alh', context: $context);
}
