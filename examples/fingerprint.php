<?php

namespace fingerprint;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Fingerprint\FileHashStrategy;

use function Castor\finder;
use function Castor\fingerprint;
use function Castor\fingerprint_exists;
use function Castor\fingerprint_save;
use function Castor\hasher;
use function Castor\run;

#[AsTask(description: 'Execute a callback only if the fingerprint has changed')]
function task_with_a_fingerprint(): void
{
    run('echo "Hello Task with Fingerprint!"');

    fingerprint(
        callback: function () {
            run('echo "Cool, no fingerprint! Executing..."');
        },
        fingerprint: my_fingerprint_check()
    );

    run('echo "Cool! I finished!"');
}

#[AsTask(description: 'Check if the fingerprint has changed before executing some code')]
function task_with_complete_fingerprint_check(): void
{
    run('echo "Hello Task with Fingerprint!"');

    if (!fingerprint_exists(my_fingerprint_check())) {
        run('echo "Cool, no fingerprint! Executing..."');
        fingerprint_save(my_fingerprint_check());
    }

    run('echo "Cool! I finished!"');
}

#[AsTask(description: 'Check if the fingerprint has changed before executing a callback (with force option)')]
function task_with_a_fingerprint_and_force(
    #[AsOption(description: 'Force the callback to run even if the fingerprint has not changed')] bool $force = false
): void {
    run('echo "Hello Task with Fingerprint!"');

    $hasRun = fingerprint(
        callback: function () {
            run('echo "Cool, no fingerprint! Executing..."');
        },
        fingerprint: my_fingerprint_check(),
        force: $force // This option will force the task to run even if the fingerprint has not changed
    );

    if ($hasRun) {
        run('echo "Fingerprint has been executed!"');
    }

    run('echo "Cool! I finished!"');
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
