<?php

namespace wait_for;

use Castor\Attribute\AsTask;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Castor\io;
use function Castor\wait_for_http_response;
use function Symfony\Component\String\u;

#[AsTask(description: 'Wait for an URL to respond with a "200" status code and a specific content')]
function url_with_specific_response_content_and_status(): void
{
    $url = $_SERVER['ENDPOINT'] ?? 'https://example.com';

    try {
        wait_for_http_response(
            url: $url,
            responseChecker: static fn (ResponseInterface $response) => 200 === $response->getStatusCode()
                    && u($response->getContent())->containsAny(['Hello World!']),
            timeout: 2,
        );
    } catch (ExitedBeforeTimeoutException) {
        io()->error("{$url} is not available. (exited before timeout)");
    } catch (TimeoutReachedException) {
        io()->error("{$url} is not available. (timeout reached)");
    }
}
