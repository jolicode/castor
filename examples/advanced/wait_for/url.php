<?php

namespace wait_for;

use Castor\Attribute\AsTask;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;

use function Castor\io;
use function Castor\wait_for_url;

#[AsTask(description: 'Wait for an URL to be available')]
function url(): void
{
    $url = $_SERVER['ENDPOINT'] ?? 'https://example.com';

    try {
        wait_for_url(url: $url, timeout: 2, message: "Waiting for {$url}...");
    } catch (ExitedBeforeTimeoutException) {
        io()->error("{$url} is not available. (exited before timeout)");
    } catch (TimeoutReachedException) {
        io()->error("{$url} is not available. (timeout reached)");
    }
}
