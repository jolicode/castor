<?php

namespace fingerprint;

use Castor\Attribute\AsTask;
use Castor\Fingerprint\FileHashStrategy;

use function Castor\finder;
use function Castor\hasher;
use function Castor\run;

#[AsTask(description: 'Run a command only if the fingerprint has changed', fingerprint: 'fingerprint\fingerprintCheck')]
function simpleTask(): void
{
    echo "Hello Simple Task !\n";
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
        ->finish()
    ;
}

// Pay attention that if the fingerprint of "simpleTask" changes, and "complexTask" is called. Nothing will be executed until the fingerprint of "complexTask" changes.
#[AsTask(description: 'Run a command only if the fingerprint has changed in called task, sub-call does not count for fingerprint', fingerprint: 'fingerprint\fingerprintCheck2')]
function complexTask(): void
{
    simpleTask();
    echo "Hello Complex Task !\n";
}

function fingerprintCheck2(): string
{
    return hasher()
        ->writeWithFinder(
            finder()
                ->in(__DIR__)
                ->name('*.fingerprint_multiple')
                ->files(),
            FileHashStrategy::Content
        )
        ->writeTask()
        ->finish()
    ;
}

#[AsTask(description: 'Run a command every time, but juste call some sub-task if fingerprint has changed')]
function inMethod(): void
{
    echo "Hello Fingerprint in Method !\n";
    run(
        'echo "Hey ! I\'m a sub-task ! Only executed if fingerprint has changed !"',
        fingerprint: hasher()
            ->writeWithFinder(
                finder()
                    ->in(__DIR__)
                    ->name('*.fingerprint_in_method')
                    ->files(),
                FileHashStrategy::Content
            )
            ->finish(),
    );
    echo "Is a thing was executed before me ?\n";
}
