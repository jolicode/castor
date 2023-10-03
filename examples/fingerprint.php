<?php

namespace fingerprint;

use Castor\Attribute\AsTask;
use Castor\Fingerprint\FileHashStrategy;

use function Castor\finder;
use function Castor\fingerprint;
use function Castor\fingerprint_exists;
use function Castor\fingerprint_save;
use function Castor\hasher;
use function Castor\run;

#[AsTask(description: 'Run a command and run part of it only if the fingerprint has changed')]
function task_with_some_fingerprint(): void
{
    run('echo "Hello Task with Fingerprint !"');

    if (!fingerprint_exists(fingerprintCheck())) {
        run('echo "Cool, no fingerprint ! Executing..."');
        fingerprint_save(fingerprintCheck());
    }

    run('echo "Cool ! I finished !"');
}

#[AsTask(description: 'Run a command only if the fingerprint has changed')]
function task_with_some_fingerprint_with_helper(): void
{
    run('echo "Hello Task with Fingerprint but with helper !"');

    fingerprint(
        callback: function () {
            run('echo "Cool, no fingerprint ! Executing..."');
        },
        fingerprint: fingerprintCheck()
    );

    run('echo "Cool ! I finished !"');
}

function fingerprintCheck(): string
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
        ->finish();
}
