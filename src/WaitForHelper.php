<?php

namespace Castor;

use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/** @internal */
class WaitForHelper
{
    /**
     * @param callable $callback function(): bool|null (return null to break the loop, true if is OK, false otherwise)
     *
     * @throws TimeoutReachedException
     * @throws ExitedBeforeTimeoutException
     */
    public static function waitFor(
        callable $callback,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = 'Waiting for callback to be available...',
    ): void {
        if (!$quiet) {
            io()->write($message);
        }

        $end = time() + $timeout;
        $elapsed = 0;

        while (time() < $end) {
            $elapsed += $intervalMs;
            $callbackResult = $callback();
            if (true === $callbackResult) {
                if (!$quiet) {
                    io()->writeln(' <fg=green>OK</>');
                    io()->newLine();
                }

                return;
            }
            if (null === $callbackResult) {
                if (!$quiet) {
                    io()->writeln(' <fg=red>FAIL</>');
                }

                throw new ExitedBeforeTimeoutException();
            }

            usleep($intervalMs * 1000);
            if (!$quiet && 0 === $elapsed % 1000) {
                io()->write('.');
            }
        }

        if (!$quiet) {
            io()->writeln(' <fg=red>FAIL</>');
        }

        log("Callback not available after {$timeout} seconds", 'error', [
            'timeout' => $timeout,
            'message' => $message,
        ]);

        throw new TimeoutReachedException(timeout: $timeout);
    }

    /**
     * @throws TimeoutReachedException
     * @throws ExitedBeforeTimeoutException
     */
    public static function waitForPort(
        int $port,
        string $host = '127.0.0.1',
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = null,
    ): void {
        self::waitFor(
            callback: function () use ($host, $port) {
                $fp = @fsockopen($host, $port, $errno, $errstr, 1);
                if ($fp) {
                    fclose($fp);

                    return true;
                }

                return false;
            },
            timeout: $timeout,
            quiet: $quiet,
            intervalMs: $intervalMs,
            message: $message ?? sprintf('Waiting for port "%s:%s" to be accessible...', $host, $port),
        );
    }

    /**
     * @throws TimeoutReachedException
     * @throws ExitedBeforeTimeoutException
     */
    public static function waitForUrl(
        string $url,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = null,
    ): void {
        self::waitFor(
            callback: function () use ($url) {
                $fp = @fopen($url, 'r');
                if ($fp) {
                    fclose($fp);

                    return true;
                }

                return false;
            },
            timeout: $timeout,
            quiet: $quiet,
            intervalMs: $intervalMs,
            message: $message ?? sprintf('Waiting for URL "%s" to be accessible...', $url),
        );
    }

    /**
     * @param ?callable $responseChecker function(ResponseInterface $response): bool
     *
     * @throws ExitedBeforeTimeoutException
     * @throws TimeoutReachedException
     */
    public static function waitForHttpStatus(
        string $url,
        int $status = 200,
        callable $responseChecker = null,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = null,
    ): void {
        self::waitFor(
            callback: function () use ($url, $status, $responseChecker) {
                try {
                    $response = http_client()->request('GET', $url);

                    if ($response->getStatusCode() !== $status) {
                        return false;
                    }
                    if ($responseChecker) {
                        // We return null to break the loop, there is no need to
                        // wait for a timeout, nothing will change at this
                        // point
                        return $responseChecker($response) ? true : null;
                    }

                    return true;
                } catch (ExceptionInterface) {
                    return false;
                }
            },
            timeout: $timeout,
            quiet: $quiet,
            intervalMs: $intervalMs,
            message: $message ?? "Waiting for URL \"{$url}\" to return HTTP status \"{$status}\"...",
        );
    }
}
