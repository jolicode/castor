<?php

namespace notify;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\notify;
use function Castor\run;
use function Castor\with;

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

#[AsTask(description: 'Sends a notification with a custom title')]
function send_notify_with_custom_title(): void
{
    notify('Hello world!'); // Will use 'Castor' by default if "notificationTitle" is not set in context

    // Set application name in context
    with(
        callback: function () {
            notify('Hello world!'); // Will use 'My App Name' by default
        },
        context: context()->withNotificationTitle('My App Name')
    );

    // Override the title of context
    notify('Hello world!', 'Custom Title'); // Will use 'Custom Title' as title, ignoring context
}
