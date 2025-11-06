<?php

namespace notify;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Sends a notification when the task finishes')]
function on_run_finished(): void
{
    run(['sleep', '0.1'], context()->withNotify());
}
