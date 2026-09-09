<?php

namespace Castor\Runner;

use Castor\ContextRegistry;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @phpstan-type SshOptions array{
 *     'port'?: int,
 *     'path_private_key'?: string,
 *     'jump_host'?: string,
 *     'multiplexing_control_path'?: string,
 *     'multiplexing_control_persist'?: string,
 *     'enable_strict_check'?: bool,
 *     'password_authentication'?: bool,
 * }
 */
final readonly class SshRunner
{
    public function __construct(
        private ProcessRunner $processRunner,
        private ContextRegistry $contextRegistry,
    ) {
    }

    /** @phpstan-param SshOptions $sshOptions */
    public function execute(
        string $command,
        ?string $path,
        string $host,
        ?string $user = null,
        array $sshOptions = [],
        ?bool $quiet = null,
        ?bool $allowFailure = null,
        ?bool $notify = null,
        ?float $timeout = null,
        ?callable $callback = null,
    ): Process {
        $ssh = $this->buildSsh($host, $user, $sshOptions);

        if ($path) {
            $command = \sprintf('cd %s && %s', self::quotePath($path), $command);
        }

        return $this->run($ssh->getExecuteCommand($command), $quiet, $allowFailure, $notify, $timeout, $callback);
    }

    /** @phpstan-param SshOptions $sshOptions */
    public function upload(
        string $sourcePath,
        string $destinationPath,
        string $host,
        ?string $user = null,
        array $sshOptions = [],
        ?bool $quiet = null,
        ?bool $allowFailure = null,
        ?bool $notify = null,
        ?float $timeout = null,
        ?callable $callback = null,
    ): Process {
        $ssh = $this->buildSsh($host, $user, $sshOptions);

        return $this->run($ssh->getUploadCommand($sourcePath, $destinationPath), $quiet, $allowFailure, $notify, $timeout, $callback);
    }

    /** @phpstan-param SshOptions $sshOptions */
    public function download(
        string $sourcePath,
        string $destinationPath,
        string $host,
        ?string $user = null,
        array $sshOptions = [],
        ?bool $quiet = null,
        ?bool $allowFailure = null,
        ?bool $notify = null,
        ?float $timeout = null,
        ?callable $callback = null,
    ): Process {
        $ssh = $this->buildSsh($host, $user, $sshOptions);

        return $this->run($ssh->getDownloadCommand($sourcePath, $destinationPath), $quiet, $allowFailure, $notify, $timeout, $callback);
    }

    private function run(
        string $command,
        ?bool $quiet = null,
        ?bool $allowFailure = null,
        ?bool $notify = null,
        ?float $timeout = null,
        ?callable $callback = null,
    ): Process {
        return $this->processRunner->run(
            $command,
            context: $this->contextRegistry
                          ->getCurrentContext()
                          ->withPty(false)
                          ->withTty(false)
                          ->withEnvironment([])
                          ->withQuiet($quiet ?? false)
                          ->withAllowFailure($allowFailure ?? false)
                          ->withNotify($notify)
                          ->withTimeout($timeout),
            callback: $callback,
        );
    }

    /**
     * The host, user and options end up unquoted in a shell command line
     * (see Ssh::getExecuteCommand()), so a value containing a shell
     * metacharacter would not be an invalid host, but a local command.
     *
     * @phpstan-param SshOptions $sshOptions
     */
    private function buildSsh(
        string $host,
        ?string $user = null,
        array $sshOptions = [],
    ): Ssh {
        self::assertShellSafe('host', $host);
        if (null !== $user) {
            self::assertShellSafe('user', $user);
        }
        foreach (['path_private_key', 'jump_host', 'multiplexing_control_path', 'multiplexing_control_persist'] as $option) {
            if (isset($sshOptions[$option])) {
                self::assertShellSafe($option, $sshOptions[$option]);
            }
        }

        $ssh = Ssh::create($user, $host, $sshOptions['port'] ?? null);

        if ($sshOptions['path_private_key'] ?? false) {
            $ssh->usePrivateKey($sshOptions['path_private_key']);
        }
        if ($sshOptions['jump_host'] ?? false) {
            $ssh->useJumpHost($sshOptions['jump_host']);
        }
        if ($sshOptions['multiplexing_control_path'] ?? false) {
            $ssh->useMultiplexing($sshOptions['multiplexing_control_path'], $sshOptions['multiplexing_control_persist'] ?? '10m');
        }
        if (isset($sshOptions['enable_strict_check'])) {
            $sshOptions['enable_strict_check'] ? $ssh->enableStrictHostKeyChecking() : $ssh->disableStrictHostKeyChecking();
        }
        if (isset($sshOptions['password_authentication'])) {
            $sshOptions['password_authentication'] ? $ssh->enablePasswordAuthentication() : $ssh->disablePasswordAuthentication();
        }

        return $ssh;
    }

    private static function assertShellSafe(string $name, string $value): void
    {
        if ('' === $value || preg_match('/[\s\'"`$\\\;&|<>(){}*?]/', $value)) {
            throw new \InvalidArgumentException(\sprintf('The ssh %s "%s" is not valid: it is empty, or contains a shell metacharacter.', $name, $value));
        }
    }

    /**
     * Quotes the remote path for the remote shell, keeping a leading "~" or
     * "~user" unquoted so that it is still expanded.
     */
    private static function quotePath(string $path): string
    {
        if (preg_match('/^(?<home>~[^\/]*)(?<rest>.*)$/s', $path, $matches)) {
            return $matches['home'] . ('' === $matches['rest'] ? '' : escapeshellarg($matches['rest']));
        }

        return escapeshellarg($path);
    }
}
