<?php

namespace Castor\Runner;

use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

use function Castor\context;

/** @internal */
final class SshRunner
{
    public function __construct(
        private ProcessRunner $processRunner,
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
            $command = sprintf('cd %s && %s', $path, $command);
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
            timeout: $timeout,
            quiet: $quiet,
            allowFailure: $allowFailure,
            notify: $notify,
            callback: $callback,
            context: context()->withPty(false)->withTty(false)->withEnvironment([]),
        );
    }

    /** @phpstan-param SshOptions $sshOptions */
    private function buildSsh(
        string $host,
        ?string $user = null,
        array $sshOptions = [],
    ): Ssh {
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
        if ($sshOptions['enable_strict_check'] ?? false) {
            $sshOptions['enable_strict_check'] ? $ssh->enableStrictHostKeyChecking() : $ssh->disableStrictHostKeyChecking();
        }
        if ($sshOptions['password_authentication'] ?? false) {
            $sshOptions['password_authentication'] ? $ssh->enablePasswordAuthentication() : $ssh->disablePasswordAuthentication();
        }

        return $ssh;
    }
}
