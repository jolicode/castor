<?php

namespace wait_for;

use Castor\Attribute\AsTask;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;

use function Castor\io;
use function Castor\wait_for_port;

#[AsTask(description: 'Wait for a service available on a port')]
function port(): void
{
    try {
        wait_for_port(host: '127.0.0.1', port: 9955, timeout: 2, message: 'Checking if 127.0.0.1 is available...');
    } catch (ExitedBeforeTimeoutException) {
        io()->error('127.0.0.1 is not available. (exited before timeout)');
    } catch (TimeoutReachedException) {
        io()->error('127.0.0.1 is not available. (timeout reached)');
    }
}
