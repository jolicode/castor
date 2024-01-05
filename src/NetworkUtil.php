<?php

namespace Castor;

class NetworkUtil
{
    public static function wait_for_port(
        int $port,
        string $host = '127.0.0.1',
        int $timeout = 30,
        string $name = null,
        bool $throw = false,
        bool $quiet = false
    ): bool {
        $msg = $name ? "Waiting for {$name} ({$host}:{$port}) to be accessible..." : "Waiting for {$host}:{$port} to be accessible...";
        false === $quiet && io()->write($msg);
        $successMessage = $name ? " <fg=green> OK {$name} ({$host}:{$port}) is accessible !</>" : " <fg=green> OK {$host}:{$port} is accessible !</>";

        $start = time();
        $end = $start + $timeout;

        while (time() < $end) {
            false === $quiet && io()->write('.');
            $fp = @fsockopen($host, $port, $errno, $errstr, 1);
            if ($fp) {
                fclose($fp);
                false === $quiet && io()->write($successMessage);
                false === $quiet && io()->newLine();

                return true;
            }
            sleep(1);
        }
        false === $quiet && io()->writeln(' <fg=red>FAIL</>');

        log("Port {$port} on {$host} not available after {$timeout} seconds", 'error', [
            'host' => $host,
            'port' => $port,
            'timeout' => $timeout,
            'errno' => $errno ?? null,
            'errstr' => $errstr ?? null,
        ]);

        if ($throw) {
            throw new \RuntimeException("Port {$port} on {$host} not available after {$timeout} seconds");
        }

        return false;
    }

    public static function wait_for_url(
        string $url,
        int $timeout = 30,
        string $name = null,
        bool $throw = false,
        bool $quiet = false
    ): bool {
        $msg = $name ? "Waiting for {$name} ({$url}) to be accessible..." : "Waiting for {$url} to be accessible...";
        false === $quiet && io()->write($msg);
        $successMessage = $name ? "{$name} ({$url}) is accessible !" : "{$url} is accessible !";

        $start = time();
        $end = $start + $timeout;

        while (time() < $end) {
            false === $quiet && io()->write('.');
            $fp = @fopen($url, 'r');
            if ($fp) {
                fclose($fp);
                false === $quiet && io()->writeln(' <fg=green>OK</>');
                false === $quiet && io()->write($successMessage);
                false === $quiet && io()->newLine();

                return true;
            }
            sleep(1);
        }

        log("URL {$url} not available after {$timeout} seconds", 'error', [
            'url' => $url,
            'timeout' => $timeout,
        ]);

        if ($throw) {
            throw new \RuntimeException("URL {$url} not available after {$timeout} seconds");
        }

        return false;
    }

    public static function wait_for_http_status(
        string $url,
        int $status = 200,
        int $timeout = 30,
        string $name = null,
        bool $throw = false,
        bool $quiet = false
    ): bool {
        $msg = $name ? "Waiting for {$name} ({$url}) to return HTTP {$status}..." : "Waiting for {$url} to return HTTP {$status}...";
        false === $quiet && io()->write($msg);

        $start = time();
        $end = $start + $timeout;

        while (time() < $end) {
            false === $quiet && io()->write('.');
            $fp = @fopen($url, 'r');
            if ($fp) {
                $meta = stream_get_meta_data($fp);
                $status = $meta['wrapper_data'][0] ?? null;
                if (str_contains($status, '200 OK')) {
                    fclose($fp);
                    false === $quiet && io()->writeln(' <fg=green>OK</>');

                    return true;
                }
            }
            sleep(1);
        }

        log("URL {$url} not available after {$timeout} seconds", 'error', [
            'url' => $url,
            'timeout' => $timeout,
        ]);

        if ($throw) {
            throw new \RuntimeException("URL {$url} not available after {$timeout} seconds");
        }

        return false;
    }
}