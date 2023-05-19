<?php

use Castor\Attribute\Task;

use function Castor\exec;
use function Castor\notify;

#[Task(description: 'Send a notification when the task finishes')]
function notifyOnFinish()
{
    exec(['sleep', '2'], notifyOnFinish: true);
}

#[Task(description: 'Send a notification')]
function send_notify()
{
    notify('Hello world!');
}
