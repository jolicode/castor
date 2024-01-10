<?php

namespace Castor;

use Castor\Exception\WaitForExitedBeforeTimeoutException;
use Castor\Exception\WaitForInvalidCallbackCheckException;
use Castor\Exception\WaitForTimeoutReachedException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Symfony\Component\String\u;

class NetworkUtil
{
    /**
     * @param callable $callback function(): bool|null (return null to break the loop, true if is OK, false otherwise)
     *
     * @throws WaitForTimeoutReachedException
     * @throws WaitForExitedBeforeTimeoutException
     */
    public static function wait_for(
        callable $callback,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = null,
    ): void {
        if (false === $quiet) {
            io()->write($message ?? 'Waiting for callback to be available...');
        }

        $start = time();
        $end = $start + $timeout;

        $elapsed = 0;
        while (time() < $end) {
            $elapsed += $intervalMs;
            if (false === $quiet && 0 === $elapsed % 1000) {
                io()->write('.');
            }
            $callbackResult = $callback();
            if (true === $callbackResult) {
                if (false === $quiet) {
                    io()->writeln(' <fg=green>OK</>');
                    io()->newLine();
                }

                return;
            }
            if (null === $callbackResult) {
                if (false === $quiet) {
                    io()->writeln(' <fg=red>FAIL</>');
                }

                throw new WaitForExitedBeforeTimeoutException();
            }
            usleep($intervalMs * 1000);
        }
        if (false === $quiet) {
            io()->writeln(' <fg=red>FAIL</>');
        }

        log("Callback not available after {$timeout} seconds", 'error', [
            'timeout' => $timeout,
        ]);

        throw new WaitForTimeoutReachedException(timeout: $timeout);
    }

    /**
     * @throws WaitForTimeoutReachedException
     * @throws WaitForExitedBeforeTimeoutException
     */
    public static function wait_for_port(
        int $port,
        string $host = '127.0.0.1',
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = null,
    ): void {
        self::wait_for(
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
            message: sprintf($message ?? 'Waiting for port %s:%s to be accessible...', $host, $port),
        );
    }

    /**
     * @throws WaitForTimeoutReachedException
     * @throws WaitForExitedBeforeTimeoutException
     */
    public static function wait_for_url(
        string $url,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = null,
    ): void {
        self::wait_for(
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
            message: sprintf($message ?? 'Waiting for URL %s to be accessible...', $url),
        );
    }

    /**
     * @param ?callable $contentCheckerCallback function(array|string $content): bool (array if application/json,
     *                                          string otherwise)
     *
     * @throws ($throw is true ? WaitForTimeoutReachedException : never)
     * @throws WaitForExitedBeforeTimeoutException
     */
    public static function wait_for_http_status(
        string $url,
        int $status = 200,
        callable $contentCheckerCallback = null,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = null,
    ): void {
        if (false === $quiet) {
            io()->write($message ?? "Waiting for URL {$url} to return HTTP status {$status}...");
        }

        $contentCheckFunction = function (ResponseInterface $response) use (
            $contentCheckerCallback,
            $url,
            $quiet,
        ): void {
            if (null === $contentCheckerCallback) {
                return;
            }
            if (false === $quiet) {
                io()->write('As content checker callback, checking content...');
            }

            $isJson = u($response->getHeaders()['content-type'][0] ?? '')->containsAny('application/json');
            $content = $isJson ? $response->toArray() : $response->getContent();
            $callbackResult = $contentCheckerCallback($content);
            if (false === $callbackResult) {
                io()->writeln(' <fg=red>FAIL</>');

                throw new WaitForInvalidCallbackCheckException(message: "HTTP Content checker callback returned false for URL {$url}");
            }
            if (false === $quiet) {
                io()->writeln(' <fg=green>Content check OK !</>');
            }
        };

        self::wait_for(
            callback: function () use ($quiet, $url, $status) {
                try {
                    io()->write('.');
                    $response = http_client()->request('GET', $url);

                    if ($response->getStatusCode() === $status) {
                        if (false === $quiet) {
                            io()->writeln(' <fg=green>OK</>');
                        }

                        return true;
                    }
                } catch (DecodingExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
                    return false;
                }

                return false;
            },
            timeout: $timeout,
            quiet: true,
            intervalMs: $intervalMs,
        );
    }
}
