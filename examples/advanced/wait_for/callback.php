<?php

namespace wait_for;

use Castor\Attribute\AsTask;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;

use function Castor\io;
use function Castor\wait_for;

#[AsTask(description: 'Use custom wait for, to check anything')]
function callback(string $thing = 'foobar'): void
{
    try {
        wait_for(
            callback: fn () => \in_array($thing, ['foo', 'bar', 'foobar'], true),
            timeout: 5,
            message: 'Waiting for my custom check...',
        );
    } catch (ExitedBeforeTimeoutException) {
        io()->error('My custom check failed. (exited before timeout)');
    } catch (TimeoutReachedException) {
        io()->error('My custom check failed. (timeout reached)');
    }
}
