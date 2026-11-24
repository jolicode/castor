<?php

namespace parallel;

use Castor\Attribute\AsTask;
use Castor\Exception\ParallelException;

use function Castor\io;
use function Castor\job;
use function Castor\parallel_map;
use function Castor\run;

#[AsTask(description: 'Runs a command for each server, two at a time, and reports the failures')]
function map(): void
{
    $commands = [
        'web1' => 'sleep 0.3; echo "Upgraded"',
        'web2' => 'sleep 1.2; echo "No space left on device" >&2; exit 1',
        'db1' => 'sleep 0.4; echo "Upgraded"',
    ];

    try {
        // The keys name the jobs, and db1 waits for web1 or web2 to finish
        parallel_map($commands, static function (string $command): void {
            // Shown next to the name of the job
            job()->setStatus('upgrading');
            run($command);
            job()->setStatus('upgraded');
        }, concurrency: 2);
    } catch (ParallelException $e) {
        // Thrown once all the servers are done, with what happened to each one
        io()->warning(\sprintf(
            'Upgraded: %s. Failed: %s.',
            implode(', ', array_keys($e->results)),
            implode(', ', array_keys($e->exceptions)),
        ));

        return;
    }

    io()->success('All the servers are upgraded.');
}
