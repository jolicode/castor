<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\parallel;
use function Castor\run;
use function Castor\with;

#[AsTask]
function reused(): void
{
    run('echo "Installing"; sleep 0.2', context: context()->withJob('install'));
    run('echo "Building"; sleep 0.2', context: context()->withJob('build'));
    run('echo "Updating"; sleep 0.2', context: context()->withJob('install'));
}

#[AsTask]
function interleaved(): void
{
    run('echo "Installing"; sleep 0.2', context: context()->withJob('install'));
    io()->writeln('Between the jobs');
    run('echo "Updating"; sleep 0.2', context: context()->withJob('install'));
    run('echo "Outside of the jobs"');
}

#[AsTask]
function failure(): void
{
    run('echo "Installing"; sleep 0.2', context: context()->withJob('install'));
    run('echo "Test 1: OK"; echo "Test 2: KO" >&2; exit 1', context: context()->withJob('test'));
}

#[AsTask]
function echoing(): void
{
    with(static function (): void {
        echo "Echoed\n";
        run('sleep 0.2');
    }, context: context(), job: 'echo');
}

#[AsTask]
function many(): void
{
    foreach (range(1, 8) as $i) {
        run("echo 'Job {$i}'; sleep 0.1", context: context()->withJob("job {$i}"));
    }
}

#[AsTask]
function nested(): void
{
    with(static function (): void {
        run('echo "Preparing"');
        parallel(
            lint: static fn () => run('echo "Linting"; sleep 0.2'),
            test: static fn () => run('echo "Testing"; sleep 0.3'),
        );
        run('echo "Reporting"');
    }, context: context(), job: 'ci');
}
