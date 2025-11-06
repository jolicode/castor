<?php

namespace notify;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\notify;
use function Castor\run;
use function Castor\with;

#[AsTask(description: 'Sends a notification')]
function notify_(): void
{
    // This will send a notification partially, this is the default behavior
    with(
        callback: function () {
            run(['sleep', '0.1']); // Will not send a notification
            notify('Hello world! (send with null)'); // Will send a notification

            run(['sleep', '0.1'], context()->withNotify()); // Will send a notification for this specific run
        },
        context: context()->withNotify(null)
    );

    // The two tasks below won't send a notification
    with(
        callback: function () {
            run(['sleep', '0.1']); // Will not send a notification
            notify('Hello world! (not send)'); // Will not send a notification

            run(['sleep', '0.1'], context()->withNotify()); // Will send a notification
        },
        context: context()->withNotify(false)
    );

    // This will send a notification
    with(
        callback: function () {
            run(['sleep', '0.1']); // Will send a notification
            notify('Hello world! (send with true)'); // Will send a notification

            run(['sleep', '0.1'], context()->withNotify(false)); // Will not send a notification for this specific run
        },
        context: context()->withNotify(true)
    );
}
