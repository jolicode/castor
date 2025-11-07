<?php

namespace fingerprint;

use Castor\Attribute\AsTask;

use function Castor\fingerprint_exists;
use function Castor\fingerprint_save;
use function Castor\io;

#[AsTask(description: 'Check if the fingerprint has changed before executing some code')]
function exist_and_save(): void
{
    io()->writeln('Hello Task with Fingerprint!');

    if (!fingerprint_exists('my_fingerprint_check', my_fingerprint_check())) {
        io()->writeln('Cool, no fingerprint! Executing...');

        fingerprint_save('my_fingerprint_check', my_fingerprint_check());
    }

    io()->writeln('Cool! I finished!');
}
