<?php

namespace wait_for;

use Castor\Attribute\AsTask;
use Castor\Exception\WaitFor\TimeoutReachedException;

use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function Castor\run;
use function Castor\wait_for_docker_container;
use function Symfony\Component\String\u;

#[AsTask(description: 'Wait for docker container to be ready')]
function docker(): void
{
    try {
        $checkLogSince = date(\DATE_RFC3339);
        run('docker run -d --rm --name helloworld alpine sh -c "echo hello world ; sleep 10"', context()->withQuiet());
        wait_for_docker_container(
            containerName: 'helloworld',
            timeout: 5,
            containerChecker: function ($containerId) use ($checkLogSince): bool {
                // Check some things (logs, command result, etc.)
                $output = capture("docker logs --since {$checkLogSince} {$containerId}", context()->withAllowFailure());

                return u($output)->containsAny(['hello world']);
            },
        );
    } catch (TimeoutReachedException) {
        io()->error('Docker container is not available. (timeout reached)');
    }
}
