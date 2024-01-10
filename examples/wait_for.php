<?php

namespace wait_for;

use Castor\Attribute\AsTask;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Castor\io;
use function Castor\wait_for;
use function Castor\wait_for_http_status;
use function Castor\wait_for_port;
use function Castor\wait_for_url;
use function Symfony\Component\String\u;

#[AsTask(description: 'Wait for a service available on a port')]
function wait_for_port_task(): void
{
    $googleIp = gethostbyname('example.com');

    try {
        wait_for_port(port: 80, host: $googleIp, timeout: 2, message: 'Checking if example.com is available...');
    } catch (ExitedBeforeTimeoutException $e) {
        io()->error('example.com is not available. (exited before timeout)');
    } catch (TimeoutReachedException $e) {
        io()->error('example.com is not available. (timeout reached)');
    }
}

#[AsTask(description: 'Wait for an URL to be available')]
function wait_for_url_task(): void
{
    try {
        wait_for_url(url: 'https://example.com', timeout: 2, message: 'Waiting for Google...');
    } catch (ExitedBeforeTimeoutException) {
        io()->error('example.com is not available. (exited before timeout)');
    } catch (TimeoutReachedException) {
        io()->error('example.com is not available. (timeout reached)');
    }
}

#[AsTask(description: 'Wait for an URL to be available with a custom content checker')]
function wait_for_url_with_content_checker_task(): void
{
    try {
        wait_for_http_status(
            url: 'https://example.com',
            status: 200,
            responseChecker: function (ResponseInterface $response) {
                return u($response->getContent())->containsAny(['Example Domain']);
            },
            timeout: 2,
        );
    } catch (TimeoutReachedException) {
        io()->error('example.com is not available. (timeout reached)');
    }
}

#[AsTask(description: 'Use custom wait for, to check anything')]
function custom_wait_for_task(int $sleep = 1): void
{
    $okAt = time() + $sleep;

    try {
        wait_for(
            callback: function () use ($okAt) {
                return time() >= $okAt;
            },
            timeout: 5,
            message: 'Waiting for my custom check...',
        );
    } catch (ExitedBeforeTimeoutException) {
        io()->error('My custom check failed. (exited before timeout)');
    } catch (TimeoutReachedException) {
        io()->error('My custom check failed. (timeout reached)');
    }
}
