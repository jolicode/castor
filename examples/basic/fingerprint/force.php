<?php

namespace fingerprint;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\fingerprint;
use function Castor\io;

#[AsTask(description: 'Check if the fingerprint has changed before executing a callback (with force option)')]
function force(
    #[AsOption(description: 'Force the callback to run even if the fingerprint has not changed')]
    bool $force = false,
): void {
    io()->writeln('Hello Task with Fingerprint!');

    $hasRun = fingerprint(
        callback: function () {
            io()->writeln('Cool, no fingerprint! Executing...');
        },
        id: 'my_fingerprint_check',
        fingerprint: my_fingerprint_check(),
        force: $force // This option will force the task to run even if the fingerprint has not changed
    );

    if ($hasRun) {
        io()->writeln('Fingerprint has been executed!');
    }

    io()->writeln('Cool! I finished!');
}
