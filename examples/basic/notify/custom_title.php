<?php

namespace notify;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\notify;
use function Castor\with;

#[AsTask(description: 'Sends a notification with a custom title')]
function custom_title(): void
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
