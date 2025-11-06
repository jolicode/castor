<?php

namespace fingerprint;

use Castor\Attribute\AsTask;

use function Castor\fingerprint;
use function Castor\io;

#[AsTask(description: 'Execute a callback only if the global fingerprint has changed (Shared across all projects)')]
function global_(): void
{
    io()->writeln('Hello Task with Global Fingerprint!');

    fingerprint(
        callback: function () {
            io()->writeln('Cool, no global fingerprint! Executing...');
        },
        id: 'my_global_fingerprint_check',
        fingerprint: my_fingerprint_check(),
        global: true, // This ensures that the fingerprint is shared across all projects (not only the current one)
    );

    io()->writeln('Cool! I finished global!');
}
