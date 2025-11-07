<?php

namespace wait_for;

use Castor\Attribute\AsTask;
use Castor\Exception\WaitFor\TimeoutReachedException;

use function Castor\io;
use function Castor\wait_for_http_status;

#[AsTask(description: 'Wait for an URL to respond with a specific status code only')]
function url_with_status_code_only(): void
{
    $url = $_SERVER['ENDPOINT'] ?? 'https://example.com';

    try {
        wait_for_http_status(
            url: $url,
            status: 200,
            timeout: 2,
        );
    } catch (TimeoutReachedException) {
        io()->error("{$url} is not available. (timeout reached)");
    }
}
