<?php

namespace Castor;

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
     */
    public static function wait_for(
        callable $callback,
        int $timeout = 10,
        string $name = null,
        bool $throw = false,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = null,
        string $successMessage = null,
    ): bool {
        if ($name) {
            if ($message) {
                $msg = sprintf($message, $name);
            } else {
                $msg = "Waiting for {$name} to be available...";
            }
            if ($successMessage) {
                $successMessage = sprintf($successMessage, $name);
            } else {
                $successMessage = " <fg=green> OK {$name} is available !</>";
            }
        } else {
            $msg = $message ?? 'Waiting for callback to be available...';
            $successMessage ??= ' <fg=green> OK callback is available !</>';
        }

        if (false === $quiet) {
            io()->write($msg);
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
                    io()->writeln($successMessage);
                    io()->newLine();
                }

                return true;
            }
            if (null === $callbackResult) {
                if (false === $quiet) {
                    io()->writeln(' <fg=red>FAIL</>');
                }

                return false;
            }
            usleep($intervalMs * 1000);
        }
        if (false === $quiet) {
            io()->writeln(' <fg=red>FAIL</>');
        }

        log("Callback not available after {$timeout} seconds", 'error', [
            'timeout' => $timeout,
        ]);

        if ($throw) {
            throw new WaitForTimeoutReachedException(name: $name ?? 'callback', timeout: $timeout);
        }

        return false;
    }

    /**
     * @throws WaitForTimeoutReachedException
     */
    public static function wait_for_port(
        int $port,
        string $host = '127.0.0.1',
        int $timeout = 10,
        string $name = null,
        bool $throw = false,
        bool $quiet = false,
        int $intervalMs = 100
    ): bool {
        if ($name) {
            $msg = "Waiting for {$name} ({$host}:{$port}) to be accessible...";
            $successMessage = " <fg=green> OK {$name} ({$host}:{$port}) is accessible !</>";
        } else {
            $msg = "Waiting for {$host}:{$port} to be accessible...";
            $successMessage = " <fg=green> OK {$host}:{$port} is accessible !</>";
        }

        return self::wait_for(
            callback: function () use ($host, $port) {
                $fp = @fsockopen($host, $port, $errno, $errstr, 1);
                if ($fp) {
                    fclose($fp);

                    return true;
                }

                return false;
            },
            timeout: $timeout,
            name: $name,
            throw: $throw,
            quiet: $quiet,
            intervalMs: $intervalMs,
            message: $msg,
            successMessage: $successMessage
        );
    }

    /**
     * @throws WaitForTimeoutReachedException
     */
    public static function wait_for_url(
        string $url,
        int $timeout = 10,
        string $name = null,
        bool $throw = false,
        bool $quiet = false,
        int $intervalMs = 100
    ): bool {
        if ($name) {
            $msg = "Waiting for {$name} ({$url}) to be accessible...";
            $successMessage = " <fg=green> OK {$name} ({$url}) is accessible !</>";
        } else {
            $msg = "Waiting for {$url} to be accessible...";
            $successMessage = " <fg=green> OK {$url} is accessible !</>";
        }

        return self::wait_for(
            callback: function () use ($url) {
                $fp = @fopen($url, 'r');
                if ($fp) {
                    fclose($fp);

                    return true;
                }

                return false;
            },
            timeout: $timeout,
            name: $name,
            throw: $throw,
            quiet: $quiet,
            intervalMs: $intervalMs,
            message: $msg,
            successMessage: $successMessage
        );
    }

    /**
     * @param ?callable $contentCheckerCallback function(array|string $content): bool (array if application/json, string otherwise)
     *
     * @throws ($throw is true ? WaitForTimeoutReachedException : never)
     */
    public static function wait_for_http_status(
        string $url,
        int $status = 200,
        callable $contentCheckerCallback = null,
        int $timeout = 10,
        string $name = null,
        bool $throw = false,
        bool $quiet = false,
        int $intervalMs = 100
    ): bool {
        if ($name) {
            $msg = "Waiting for {$name} ({$url}) to return HTTP {$status}...";
            $successMessage = " <fg=green> OK {$name} ({$url}) returned HTTP {$status} !</>";
        } else {
            $msg = "Waiting for {$url} to return HTTP {$status}...";
            $successMessage = " <fg=green> OK {$url} returned HTTP {$status} !</>";
        }

        if (false === $quiet) {
            io()->write($msg);
        }

        $contentCheckFunction = function (ResponseInterface $response) use (
            $contentCheckerCallback,
            $url,
            $name,
            $quiet,
            $throw
        ): ?bool {
            if (null === $contentCheckerCallback) {
                return true;
            }
            if (false === $quiet) {
                io()->write('As content checker callback, checking content...');
            }

            $isJson = u($response->getHeaders()['content-type'][0] ?? '')->containsAny('application/json');
            $content = $isJson ? $response->toArray() : $response->getContent();
            $callbackResult = $contentCheckerCallback($content);
            if (false === $callbackResult) {
                if ($throw) {
                    throw new WaitForInvalidCallbackCheckException($name ?? $url);
                }
                if (false === $quiet) {
                    io()->writeln(' <fg=red>FAIL</>');

                    return null;
                }
            }
            if (false === $quiet) {
                io()->writeln(' <fg=green>Content check OK !</>');
            }

            return true;
        };

        return self::wait_for(
            callback: function () use ($contentCheckFunction, $quiet, $successMessage, $url, $status) {
                try {
                    io()->write('.');
                    $response = http_client()->request('GET', $url);

                    if ($response->getStatusCode() === $status) {
                        if (false === $quiet) {
                            io()->writeln($successMessage);
                        }

                        return $contentCheckFunction($response);
                    }
                } catch (DecodingExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
                    return false;
                }

                return false;
            },
            timeout: $timeout,
            name: $name,
            throw: $throw,
            quiet: true,
            intervalMs: $intervalMs,
        );
    }
}
