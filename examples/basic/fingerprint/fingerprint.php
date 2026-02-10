<?php

namespace fingerprint;

use Castor\Attribute\AsTask;
use Castor\Fingerprint\FileHashStrategy;

use function Castor\finder;
use function Castor\fingerprint;
use function Castor\hasher;
use function Castor\io;

#[AsTask(description: 'Execute a callback only if the fingerprint has changed')]
function fingerprint_(): void
{
    io()->writeln('Hello Task with Fingerprint!');

    fingerprint(
        callback: static function () {
            io()->writeln('Cool, no fingerprint! Executing...');
        },
        id: 'my_fingerprint_check',
        fingerprint: my_fingerprint_check(),
    );

    io()->writeln('Cool! I finished!');
}

function my_fingerprint_check(): string
{
    return hasher()
        ->writeWithFinder(
            finder()
                ->in(__DIR__)
                ->name('*.fingerprint_single')
                ->files(),
            FileHashStrategy::Content
        )
        ->writeTask()
        ->finish()
    ;
}
