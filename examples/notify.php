<?php

namespace notify;

use Castor\Attribute\AsTask;

use function Castor\notify;
use function Castor\run;

#[AsTask(description: 'Sends a notification when the task finishes')]
function notify_on_finish(): void
{
    run(['sleep', '1'], notify: true);
}

#[AsTask(description: 'Sends a notification')]
function send_notify(): void
{
    notify('Hello world!');
}
