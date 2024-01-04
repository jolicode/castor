<?php

namespace Castor;

class NetworkUtil
{
    /**
     * Check if a port is accessible for a host.
     *
     * @param int         $port    The port to check
     * @param string      $host    The host to check (default to 127.0.0.1)
     * @param int         $timeout The timeout in seconds
     * @param string|null $name    The name of the port (will be displayed in the output when $quiet is false)
     * @param bool        $throw   If true, will throw an exception if the port is not accessible instead of returning
     * @param bool        $quiet   If true, will not output anything
     *
     * @return bool True if the port is accessible, false otherwise
     */
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

    /**
     * Check if a URL is accessible.
     *
     * @param string      $url     The URL to check
     * @param int         $timeout The timeout in seconds
     * @param string|null $name    The name of the URL (will be displayed in the output when $quiet is false)
     * @param bool        $throw   If true, will throw an exception if the URL is not accessible instead of returning
     * @param bool        $quiet   If true, will not output anything
     *
     * @return bool True if the URL is accessible, false otherwise
     */
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

    /**
     * @param string      $url     The URL to check
     * @param int         $status  The HTTP status to check
     * @param int         $timeout The timeout in seconds
     * @param string|null $name    The name of the URL (will be displayed in the output when $quiet is false)
     * @param bool        $throw   If true, will throw an exception if the URL is not accessible instead of returning
     * @param bool        $quiet   If true, will not output anything
     */
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

    /**
     * @param string        $container       The container name (service name if $useComposeName is true, otherwise
     *                                       container name)
     * @param array<int>    $portsToCheck    The ports to check (if empty, will check all ports of the container
     *                                       exposed to the host)
     * @param int           $timeout         The timeout in seconds
     * @param bool          $autoStart       If true, will start the container if not running or not found
     * @param bool          $throw           If true, will throw an exception if the container is not accessible
     *                                       instead of returning false
     * @param bool          $quiet           If true, will not output anything
     * @param callable|null $additionalCheck A callable that will be called after the ports check, if it returns true,
     *                                       the container will be considered as accessible
     * @param bool          $useComposeName  If true, will use docker compose commands instead of docker commands
     *
     * @return bool True if the container is accessible, false otherwise
     */
    public static function wait_for_docker_container(
        string $container,
        array $portsToCheck = [],
        int $timeout = 30,
        bool $autoStart = true,
        bool $throw = false,
        bool $quiet = false,
        callable $additionalCheck = null,
        bool $useComposeName = true
    ): bool {
        if (false === command_exists('docker')) {
            log('Docker is not installed', 'error');
            if ($throw) {
                throw new \RuntimeException('Docker is not installed');
            }

            return false;
        }

        $start = time();
        $end = $start + $timeout;
        $msg = "Waiting for container {$container} to be up...";
        false === $quiet && io()->write($msg);
        $successMessage = "Container {$container} is accessible !";

        $isContainerExist = !empty(capture("docker ps -a -q -f name={$container}", allowFailure: true));

        if (!$isContainerExist) {
            false === $quiet && io()->write(' <fg=yellow>Container not found</>');
            if (!$autoStart) {
                false === $quiet && io()->writeln(' <fg=red>FAIL</>');
                log("Container {$container} not found", 'error', [
                    'container' => $container,
                ]);
                if ($throw) {
                    throw new \RuntimeException("Container {$container} not found");
                }

                return false;
            }
        }

        $isContainerRunning = !empty(capture("docker ps -q -f name={$container}", allowFailure: true));

        if (!$isContainerRunning) {
            false === $quiet && io()->writeln(' <fg=yellow>Container not running</>');
            if (!$autoStart) {
                false === $quiet && io()->writeln(' <fg=red>FAIL</>');
                log("Container {$container} not running", 'error', [
                    'container' => $container,
                ]);
                if ($throw) {
                    throw new \RuntimeException("Container {$container} not running");
                }

                return false;
            }

            false === $quiet && io()->write("[autostart is enabled] Starting container {$container}...");

            try {
                $cmd = $useComposeName ? "docker compose up -d --wait {$container}" : "docker start {$container}";
                $process = run($cmd, quiet: true, allowFailure: true);

                if (false === $process->isSuccessful()) {
                    false === $quiet && io()->writeln(' <fg=red>FAIL</>');
                    log("Container {$container} failed to start", 'error', [
                        'container' => $container,
                        'process' => $process,
                    ]);
                    if ($throw) {
                        throw new \RuntimeException("Container {$container} failed to start");
                    }

                    return false;
                }
            } catch (\Throwable $e) {
                false === $quiet && io()->writeln(' <fg=red>FAIL</>');
                log("Container {$container} failed to start", 'error', [
                    'container' => $container,
                    'exception' => $e,
                ]);
                if ($throw) {
                    throw new \RuntimeException("Container {$container} failed to start");
                }

                return false;
            }
            false === $quiet && io()->writeln(' <fg=green>OK</>');
        } else {
            false === $quiet && io()->writeln(' <fg=green>OK</>');
        }

        if ([] === $portsToCheck) {
            $containerId = capture(
                $useComposeName ? "docker compose ps -q {$container}" : "docker ps -q -f name={$container}"
            );
            $output = capture("docker inspect {$containerId} --format='{{json .HostConfig.PortBindings}}'");
            $portsBindings = json_decode($output, true);

            if (empty($portsBindings)) {
                log("Container {$container} has no port bindings", 'error', [
                    'container' => $container,
                ]);
                false === $quiet && io()->writeln(' <fg=red>FAIL</>');
                if ($throw) {
                    throw new \RuntimeException("Container {$container} has no port bindings, please specify ports to check");
                }

                return false;
            }

            foreach ($portsBindings as $portBindings) {
                foreach ($portBindings as $portBinding) {
                    if (empty($portBinding['HostPort'])) {
                        continue;
                    }
                    $portsToCheck[] = $portBinding['HostPort'];
                }
            }
        }

        io()->writeln('Checking ports : ' . implode(', ', $portsToCheck));
        foreach ($portsToCheck as $port) {
            $success = self::wait_for_port(
                host: 'localhost',
                port: (int) $port,
                timeout: $timeout,
                throw: $throw,
                quiet: $quiet
            );
            if (!$success) {
                return false;
            }
        }

        if (null !== $additionalCheck) {
            false === $quiet && io()->write('Additional check...');
            while (time() < $end) {
                try {
                    false === $quiet && io()->write('.');
                    $success = $additionalCheck($container);
                    if ($success) {
                        false === $quiet && io()->writeln(' <fg=green>OK</>');
                        false === $quiet && io()->writeln($successMessage);

                        return true;
                    }
                } catch (\Throwable $e) {
                    log("Container {$container} additional check failed", 'debug', [
                        'container' => $container,
                        'exception' => $e->getMessage(),
                    ]);
                }
                sleep(1);
            }
            false === $quiet && io()->writeln(' <fg=red>FAIL</>');

            return false;
        }

        false === $quiet && io()->writeln($successMessage);

        return true;
    }
}
