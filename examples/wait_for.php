<?php

namespace wait_for;

use Castor\Attribute\AsTask;
use Castor\Exception\WaitForExitedBeforeTimeoutException;
use Castor\Exception\WaitForTimeoutReachedException;

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
    } catch (WaitForExitedBeforeTimeoutException $e) {
        io()->error('example.com is not available. (exited before timeout)');
    } catch (WaitForTimeoutReachedException $e) {
        io()->error('example.com is not available. (timeout reached)');
    }
}

#[AsTask(description: 'Wait for an URL to be available')]
function wait_for_url_task(): void
{
    try {
        wait_for_url(url: 'https://example.com', timeout: 2, message: 'Waiting for Google...');
    } catch (WaitForExitedBeforeTimeoutException $e) {
        io()->error('example.com is not available. (exited before timeout)');
    } catch (WaitForTimeoutReachedException $e) {
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
            contentCheckerCallback: function (string $content) {
                return u($content)->containsAny(['Example Domain']);
            },
            timeout: 2,
        );
    } catch (WaitForTimeoutReachedException $e) {
        io()->error('example.com is not available. (timeout reached)');
    }
}

#[AsTask(description: 'Use custom wait for, to check anything')]
function custom_wait_for_task(): void
{
    $tmpFilePath = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'castor-wait-for-custom.tmp';
    $endTime = time() + 1;
    $fiber = new \Fiber(function () use ($endTime, $tmpFilePath) {
        while (time() < $endTime) {
            \Fiber::suspend();
        }

        touch($tmpFilePath);
    });

    try {
        wait_for(
            callback: function () use ($tmpFilePath, $fiber) {
                if (!$fiber->isStarted()) {
                    $fiber->start();
                }
                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }

                return file_exists($tmpFilePath);
            },
            timeout: 2,
            message: 'Waiting for my custom check...',
        );
    } catch (WaitForExitedBeforeTimeoutException $e) {
        io()->error('My custom check failed. (exited before timeout)');
    } catch (WaitForTimeoutReachedException $e) {
        io()->error('My custom check failed. (timeout reached)');
    }

    unlink($tmpFilePath);
}
