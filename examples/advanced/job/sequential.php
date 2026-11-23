<?php

namespace job;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;
use function Castor\with;

#[AsTask(description: 'Organizes the output of the commands of a task in jobs, one after the other')]
function sequential(bool $fail = false): void
{
    // The commands of a job are shown together, under its name
    run('sleep 0.3; echo "Resolving dependencies"; sleep 0.3; echo "Installed 42 packages"', context: context()->withJob('install'));

    // with() runs a whole function in a job: its output and the one of the
    // commands it runs are organized under its name
    with(static function () use ($fail): void {
        io()->writeln('Running the test suite');
        run('sleep 0.3; echo "Test 1: OK"');
        if ($fail) {
            run('echo "Test 2: KO" >&2; exit 1');
        }
    }, context: context(), job: 'test');

    // A job can run again: its output is shown with the one of its first run
    run('sleep 0.3; echo "Dependencies are up to date"', context: context()->withJob('install'));

    io()->success('Done.');
}
