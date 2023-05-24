<?php

namespace notify;

use Castor\Attribute\AsTask;

use function Castor\exec;
use function Castor\notify;

#[AsTask(description: 'Send a notification when the task finishes')]
function notify_on_finish()
{
    exec(['sleep', '2'], notify: true);
}

#[AsTask(description: 'Send a notification')]
function send_notify()
{
    notify('Hello world!');
}
