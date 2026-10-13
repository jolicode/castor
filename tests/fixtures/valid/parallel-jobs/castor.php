<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\logger;
use function Castor\parallel;
use function Castor\run;

#[AsTask]
function success(): void
{
    parallel(
        lint: static fn () => run('echo "Linting"; sleep 0.2'),
        test: static fn () => run('echo "Testing"; sleep 0.4'),
    );
}

#[AsTask]
function failure(): void
{
    parallel(
        lint: static fn () => run('sleep 0.4; echo "Linted"'),
        test: static function (): void {
            logger()->warning('The tests are slow.');
            run('echo "Test 1: OK"; sleep 0.2; echo "Test 2: KO" >&2; exit 1');
        },
    );
}

#[AsTask]
function siblings(): void
{
    parallel(
        static fn () => parallel(a: static fn () => run('sleep 0.4; echo "From a"'), b: static fn () => run('sleep 0.2; echo "From b"')),
        static fn () => parallel(c: static fn () => run('sleep 0.3; echo "From c"'), d: static fn () => run('sleep 0.5; echo "From d"')),
    );
}

#[AsTask]
function echoing(): void
{
    parallel(
        lint: static function (): void {
            echo "Echoed\n";
            run('sleep 0.2; exit 1');
        },
    );
}

#[AsTask]
function slow(): void
{
    parallel(
        lint: static fn () => run('echo "Linting"; sleep 5'),
    );
}
