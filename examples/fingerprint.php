<?php

namespace fingerprint;

use Castor\Attribute\AsTask;
use Symfony\Component\Finder\Finder;

use function Castor\run_with_fingerprint;

#[AsTask(description: 'Run a command only if the fingerprint has changed', fingerprint: 'fingerprint\fingerprintCheck')]
function simpleTask(): void
{
    echo "Hello Simple Task !\n";
}

function fingerprintCheck(): Finder
{
    return (new Finder())
        ->in(__DIR__)
        ->name('*.fingerprint_single')
        ->files()
    ;
}

// Pay attention that if the fingerprint of "simpleTask" changes, and "complexTask" is called. Nothing will be executed until the fingerprint of "complexTask" changes.
#[AsTask(description: 'Run a command only if the fingerprint has changed in called task, sub-call does not count for fingerprint', fingerprint: 'fingerprint\fingerprintCheck2')]
function complexTask(): void
{
    simpleTask();
    echo "Hello Complex Task !\n";
}

function fingerprintCheck2(): Finder
{
    return (new Finder())
        ->in(__DIR__)
        ->name('*.fingerprint_multiple')
        ->files()
    ;
}

#[AsTask(description: 'Run a command every time, but juste call some sub-task if fingerprint has changed')]
function inMethod(): void
{
    echo "Hello Fingerprint in Method !\n";
    run_with_fingerprint(
        (new Finder())
            ->in(__DIR__)
            ->name('*.fingerprint_in_method')
            ->files(),
        function () {
            echo "Hey ! I'm a sub-task ! Only executed if fingerprint has changed !\n";
        },
        function () {
            echo "Hey ! I'm a sub-task ! But i already have been executed !\n";
        }
    );
    echo "Is a thing was executed before me ?\n";
}
