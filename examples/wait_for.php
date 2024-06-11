<?php

namespace wait_for;

use Castor\Attribute\AsTask;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function Castor\run;
use function Castor\wait_for;
use function Castor\wait_for_docker_container;
use function Castor\wait_for_http_response;
use function Castor\wait_for_http_status;
use function Castor\wait_for_port;
use function Castor\wait_for_url;
use function Symfony\Component\String\u;

#[AsTask(description: 'Wait for a service available on a port')]
function wait_for_port_task(): void
{
    try {
        wait_for_port(host: '127.0.0.1', port: 9955, timeout: 2, message: 'Checking if 127.0.0.1 is available...');
    } catch (ExitedBeforeTimeoutException) {
        io()->error('127.0.0.1 is not available. (exited before timeout)');
    } catch (TimeoutReachedException) {
        io()->error('127.0.0.1 is not available. (timeout reached)');
    }
}

#[AsTask(description: 'Wait for an URL to be available')]
function wait_for_url_task(): void
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

#[AsTask(description: 'Wait for an URL to respond with a "200" status code and a specific content')]
function wait_for_url_with_specific_response_content_and_status(): void
{
    $url = $_SERVER['ENDPOINT'] ?? 'https://example.com';

    try {
        wait_for_http_response(
            url: $url,
            responseChecker: fn (ResponseInterface $response) => 200 === $response->getStatusCode()
                    && u($response->getContent())->containsAny(['Hello World!']),
            timeout: 2,
        );
    } catch (ExitedBeforeTimeoutException) {
        io()->error("{$url} is not available. (exited before timeout)");
    } catch (TimeoutReachedException) {
        io()->error("{$url} is not available. (timeout reached)");
    }
}

#[AsTask(description: 'Wait for an URL to respond with a specific status code only')]
function wait_for_url_with_status_code_only(): void
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

#[AsTask(description: 'Use custom wait for, to check anything')]
function custom_wait_for_task(string $thing = 'foobar'): void
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

#[AsTask(description: 'Wait for docker container to be ready')]
function wait_for_docker_container_task(): void
{
    try {
        run('docker run -d --rm --name helloworld alpine sh -c "echo hello world ; sleep 10"', context: context()->withQuiet());
        wait_for_docker_container(
            containerName: 'helloworld',
            timeout: 5,
            containerChecker: function ($containerId): bool {
                // Check some things (logs, command result, etc.)
                $output = capture("docker logs {$containerId}", context: context()->withAllowFailure());

                return u($output)->containsAny(['hello world']);
            },
        );
    } catch (TimeoutReachedException) {
        io()->error('Docker container is not available. (timeout reached)');
    }
}
