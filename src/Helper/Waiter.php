<?php

namespace Castor\Helper;

use Castor\Exception\ExecutableNotFoundException;
use Castor\Exception\WaitFor\DockerContainerStateException;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;
use Castor\Runner\ProcessRunner;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Castor\context;

/** @internal */
final class Waiter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ProcessRunner $processRunner,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param callable $callback function(): bool|null (return null to break the loop, true if is OK, false otherwise)
     *
     * @throws TimeoutReachedException
     * @throws ExitedBeforeTimeoutException
     */
    public function waitFor(
        SymfonyStyle $io,
        callable $callback,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        string $message = 'Waiting for callback to be available...',
    ): void {
        if (!$quiet) {
            $io->write($message);
        }

        $end = time() + $timeout;
        $elapsed = 0;

        while (time() < $end) {
            $elapsed += $intervalMs;
            $callbackResult = $callback();
            if (true === $callbackResult) {
                if (!$quiet) {
                    $io->writeln(' <fg=green>OK</>');
                    $io->newLine();
                }

                return;
            }
            if (null === $callbackResult) {
                if (!$quiet) {
                    $io->writeln(' <fg=red>FAIL</>');
                }

                throw new ExitedBeforeTimeoutException();
            }

            usleep($intervalMs * 1000);
            if (!$quiet && 0 === $elapsed % 1000) {
                $io->write('.');
            }
        }

        if (!$quiet) {
            $io->writeln(' <fg=red>FAIL</>');
        }

        $this->logger->error("Callback not available after {$timeout} seconds", [
            'timeout' => $timeout,
            'message' => $message,
        ]);

        throw new TimeoutReachedException(timeout: $timeout);
    }

    /**
     * @throws TimeoutReachedException
     * @throws ExitedBeforeTimeoutException
     */
    public function waitForPort(
        SymfonyStyle $io,
        int $port,
        string $host = '127.0.0.1',
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        ?string $message = null,
    ): void {
        $this->waitFor(
            io: $io,
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
    public function waitForUrl(
        SymfonyStyle $io,
        string $url,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        ?string $message = null,
    ): void {
        $this->waitFor(
            io: $io,
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
     * @throws ExitedBeforeTimeoutException
     * @throws TimeoutReachedException
     */
    public function waitForHttpStatus(
        SymfonyStyle $io,
        string $url,
        int $status = 200,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        ?string $message = null,
    ): void {
        $this->waitForHttpResponse(
            io: $io,
            url: $url,
            responseChecker: function (ResponseInterface $response) use ($status) {
                return $response->getStatusCode() === $status;
            },
            timeout: $timeout,
            quiet: $quiet,
            intervalMs: $intervalMs,
            message: $message ?? "Waiting for URL \"{$url}\" to return HTTP status \"{$status}\"...",
        );
    }

    /**
     * @param ?callable $responseChecker function(ResponseInterface $response): bool
     *
     * @throws ExitedBeforeTimeoutException
     * @throws TimeoutReachedException
     */
    public function waitForHttpResponse(
        SymfonyStyle $io,
        string $url,
        ?callable $responseChecker = null,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        ?string $message = null,
    ): void {
        $this->waitFor(
            io: $io,
            callback: function () use ($url, $responseChecker) {
                try {
                    $response = $this->httpClient->request('GET', $url);

                    if ($responseChecker) {
                        // We return null to break the loop, there is no need to
                        // wait for a timeout, nothing will change at this
                        // point
                        $responseFromChecker = $responseChecker($response);
                        if (null === $responseFromChecker) {
                            return null;
                        }

                        return $responseFromChecker;
                    }

                    return true;
                } catch (ExceptionInterface) {
                    return false;
                }
            },
            timeout: $timeout,
            quiet: $quiet,
            intervalMs: $intervalMs,
            message: $message ?? "Waiting for URL \"{$url}\" to return HTTP response...",
        );
    }

    /**
     * @param array<int> $portsToCheck
     *
     * @throws TimeoutReachedException
     */
    public function waitForDockerContainer(
        SymfonyStyle $io,
        string $containerName,
        int $timeout = 10,
        bool $quiet = false,
        int $intervalMs = 100,
        ?string $message = null,
        ?callable $containerChecker = null,
        array $portsToCheck = [],
    ): void {
        if (null === (new ExecutableFinder())->find('docker')) {
            throw new ExecutableNotFoundException('docker');
        }
        $this->waitFor(
            io: $io,
            callback: function () use ($timeout, $io, $portsToCheck, $containerChecker, $containerName) {
                $containerId = $this->processRunner->capture("docker ps -a -q --filter name={$containerName}", context: context()->withAllowFailure());
                $isContainerExist = (bool) $containerId;
                $isContainerRunning = (bool) $this->processRunner->capture("docker inspect -f '{{.State.Running}}' {$containerId}", context: context()->withAllowFailure());

                if (false === $isContainerExist) {
                    throw new DockerContainerStateException($containerName, 'not exist');
                }

                if (false === $isContainerRunning) {
                    throw new DockerContainerStateException($containerName, 'not running');
                }

                foreach ($portsToCheck as $port) {
                    $this->waitForPort(io: $io, port: $port, timeout: $timeout, quiet: true);
                }

                if (null !== $containerChecker) {
                    try {
                        $this->waitFor(
                            io: $io,
                            callback: function () use ($containerChecker, $containerId) {
                                return $containerChecker($containerId);
                            },
                            timeout: $timeout,
                            quiet: true,
                        );
                    } catch (TimeoutReachedException) {
                        return false;
                    }
                }

                return true;
            },
            timeout: $timeout,
            quiet: $quiet,
            intervalMs: $intervalMs,
            message: sprintf($message ?? 'Waiting for docker container "%s" to be available...', $containerName),
        );
    }
}
