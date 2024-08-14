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
    run(['sleep', '0.1'], context: context()->withNotify());
}

#[AsTask(description: 'Sends a notification')]
function send_notify(): void
{
    // This will send a notification partially, this is the default behavior
    with(
        callback: function () {
            run(['sleep', '0.1']); // Will not send a notification
            notify('Hello world! (send with null)'); // Will send a notification

            run(['sleep', '0.1'], context: context()->withNotify()); // Will send a notification for this specific run
        },
        context: context()->withNotify(null)
    );

    // The two tasks below won't send a notification
    with(
        callback: function () {
            run(['sleep', '0.1']); // Will not send a notification
            notify('Hello world! (not send)'); // Will not send a notification

            run(['sleep', '0.1'], context: context()->withNotify()); // Will send a notification
        },
        context: context()->withNotify(false)
    );

    // This will send a notification
    with(
        callback: function () {
            run(['sleep', '0.1']); // Will send a notification
            notify('Hello world! (send with true)'); // Will send a notification

            run(['sleep', '0.1'], context: context()->withNotify(false)); // Will not send a notification for this specific run
        },
        context: context()->withNotify(true)
    );
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
