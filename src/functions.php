<?php

namespace Castor;

use Castor\Console\Application;
use Castor\Exception\MinimumVersionRequirementNotMetException;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;
use Joli\JoliNotif\Notification;
use Joli\JoliNotif\NotifierFactory;
use JoliCode\PhpOsHelper\OsHelper;
use Monolog\Logger;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Spatie\Ssh\Ssh;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Symfony\Component\String\u;

/**
 * @return array<mixed>
 */
function parallel(callable ...$callbacks): array
{
    /** @var \Fiber[] $fibers */
    $fibers = [];
    $exceptions = [];

    foreach ($callbacks as $callback) {
        $fiber = new \Fiber($callback);

        try {
            $fiber->start();
        } catch (\Throwable $e) {
            $app = app();
            $output = output();

            if ($output instanceof ConsoleOutput) {
                $output = $output->getErrorOutput();
            }

            $app->renderThrowable($e, $output);

            $exceptions[] = $e;
        }

        $fibers[] = $fiber;
    }

    $isRunning = true;

    while ($isRunning) {
        $isRunning = false;

        foreach ($fibers as $fiber) {
            $isRunning = $isRunning || !$fiber->isTerminated();

            if (!$fiber->isTerminated() && $fiber->isSuspended()) {
                try {
                    $fiber->resume();
                } catch (\Throwable $e) {
                    $app = app();
                    $output = output();

                    if ($output instanceof ConsoleOutput) {
                        $output = $output->getErrorOutput();
                    }

                    $app->renderThrowable($e, $output);

                    $exceptions[] = $e;
                }
            }
        }

        if (\Fiber::getCurrent()) {
            \Fiber::suspend();
            usleep(1_000);
        }
    }

    if ($exceptions) {
        throw new \RuntimeException('One or more exceptions were thrown in parallel.');
    }

    return array_map(fn ($fiber) => $fiber->getReturn(), $fibers);
}

/**
 * @param string|array<string|\Stringable|int>           $command
 * @param array<string, string|\Stringable|int>|null     $environment
 * @param (callable(string, string, Process) :void)|null $callback
 */
function run(
    string|array $command,
    ?array $environment = null,
    ?string $path = null,
    ?bool $tty = null,
    ?bool $pty = null,
    ?float $timeout = null,
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?callable $callback = null,
    ?Context $context = null,
): Process {
    $context ??= GlobalHelper::getContext();

    if (null !== $environment) {
        $context = $context->withEnvironment($environment);
    }

    if ($path) {
        $context = $context->withPath($path);
    }

    if (null !== $tty) {
        $context = $context->withTty($tty);
    }

    if (null !== $pty) {
        $context = $context->withPty($pty);
    }

    if (null !== $timeout) {
        $context = $context->withTimeout($timeout);
    }

    if (null !== $quiet) {
        $context = $context->withQuiet($quiet);
    }

    if (null !== $allowFailure) {
        $context = $context->withAllowFailure($allowFailure);
    }

    if (null !== $notify) {
        $context = $context->withNotify($notify);
    }

    if (\is_array($command)) {
        $process = new Process($command, $context->currentDirectory, $context->environment, null, $context->timeout);
    } else {
        $process = Process::fromShellCommandline($command, $context->currentDirectory, $context->environment, null, $context->timeout);
    }

    // When quiet is set, it means we want to capture the output.
    // So we disable TTY and PTY because it does not make sens otherwise (and it's buggy).
    if ($context->quiet) {
        if ($tty) {
            throw new \LogicException('The "tty" argument cannot be used with "quiet".');
        }
        if ($pty) {
            throw new \LogicException('The "pty" argument cannot be used with "quiet".');
        }
        $context = $context
            ->withTty(false)
            ->withPty(false)
        ;
    }

    if ($context->tty) {
        $process->setTty(true);
        $process->setInput(\STDIN);
    } elseif ($context->pty) {
        $process->setPty(true);
        $process->setInput(\STDIN);
    }

    if (!$context->quiet && !$callback) {
        $callback = static function ($type, $bytes, $process) {
            GlobalHelper::getSectionOutput()->writeProcessOutput($type, $bytes, $process);
        };
    }

    log(sprintf('Running command: "%s".', $process->getCommandLine()), 'info', [
        'process' => $process,
    ]);

    GlobalHelper::getSectionOutput()->initProcess($process);

    $process->start(function ($type, $bytes) use ($callback, $process) {
        if ($callback) {
            $callback($type, $bytes, $process);
        }
    });

    if (\Fiber::getCurrent()) {
        while ($process->isRunning()) {
            GlobalHelper::getSectionOutput()->tickProcess($process);
            \Fiber::suspend();
            usleep(20_000);
        }
    }

    $exitCode = $process->wait();
    GlobalHelper::getSectionOutput()->finishProcess($process);

    if ($context->notify) {
        notify(sprintf('The command "%s" has been finished %s.', $process->getCommandLine(), 0 === $exitCode ? 'successfully' : 'with an error'));
    }

    if (0 !== $exitCode) {
        log(sprintf('Command finished with an error (exit code=%d).', $process->getExitCode()), 'notice');
        if (!$context->allowFailure) {
            if ($context->verbosityLevel->isVeryVerbose()) {
                throw new ProcessFailedException($process);
            }

            throw fix_exception(new \Exception("The command \"{$process->getCommandLine()}\" failed."));
        }

        return $process;
    }

    log('Command finished successfully.', 'debug');

    return $process;
}

/**
 * @param string|array<string|\Stringable|int>       $command
 * @param array<string, string|\Stringable|int>|null $environment
 */
function capture(
    string|array $command,
    ?array $environment = null,
    ?string $path = null,
    ?float $timeout = null,
    ?bool $allowFailure = null,
    ?string $onFailure = null,
    ?Context $context = null,
): string {
    $hasOnFailure = null !== $onFailure;
    if ($hasOnFailure) {
        if (null !== $allowFailure) {
            throw new \LogicException('The "allowFailure" argument cannot be used with "onFailure".');
        }
        $allowFailure = true;
    }

    $process = run(
        command: $command,
        environment: $environment,
        path: $path,
        timeout: $timeout,
        allowFailure: $allowFailure,
        context: $context,
        quiet: true,
    );

    if ($hasOnFailure && !$process->isSuccessful()) {
        return $onFailure;
    }

    return trim($process->getOutput());
}

/**
 * @param string|array<string|\Stringable|int>       $command
 * @param array<string, string|\Stringable|int>|null $environment
 */
function exit_code(
    string|array $command,
    ?array $environment = null,
    ?string $path = null,
    ?float $timeout = null,
    ?bool $quiet = null,
    ?Context $context = null,
): int {
    $process = run(
        command: $command,
        environment: $environment,
        path: $path,
        timeout: $timeout,
        allowFailure: true,
        context: $context,
        quiet: $quiet,
    );

    return $process->getExitCode() ?? 0;
}

function get_exit_code(...$args): int
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'exit_code');

    return exit_code(...$args);
}

/**
 * This function is considered experimental and may change in the future.
 *
 * @param array{
 *     'port'?: int,
 *     'path_private_key'?: string,
 *     'jump_host'?: string,
 *     'multiplexing_control_path'?: string,
 *     'multiplexing_control_persist'?: string,
 *     'enable_strict_check'?: bool,
 *     'password_authentication'?: bool,
 * } $sshOptions
 */
function ssh_run(
    string $command,
    string $host,
    string $user,
    array $sshOptions = [],
    ?string $path = null,
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?float $timeout = null,
): Process {
    $ssh = ssh_configuration($host, $user, $sshOptions);

    if ($path) {
        $command = sprintf('cd %s && %s', $path, $command);
    }

    return run(
        $ssh->getExecuteCommand($command),
        environment: [],
        tty: false,
        pty: false,
        timeout: $timeout,
        quiet: $quiet,
        allowFailure: $allowFailure,
        notify: $notify
    );
}

function ssh(...$args): Process
{
    trigger_deprecation('jolicode/castor', '0.10', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'ssh_run');

    return ssh_run(...$args);
}

/**
 * This function is considered experimental and may change in the future.
 *
 * @param array{
 *     'port'?: int,
 *     'path_private_key'?: string,
 *     'jump_host'?: string,
 *     'multiplexing_control_path'?: string,
 *     'multiplexing_control_persist'?: string,
 *     'enable_strict_check'?: bool,
 *     'password_authentication'?: bool,
 * } $sshOptions
 */
function ssh_upload(
    string $sourcePath,
    string $destinationPath,
    string $host,
    string $user,
    array $sshOptions = [],
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?float $timeout = null,
): Process {
    $ssh = ssh_configuration($host, $user, $sshOptions);

    return run(
        $ssh->getUploadCommand($sourcePath, $destinationPath),
        environment: [],
        tty: false,
        pty: false,
        timeout: $timeout,
        quiet: $quiet,
        allowFailure: $allowFailure,
        notify: $notify
    );
}

/**
 * This function is considered experimental and may change in the future.
 *
 * @param array{
 *     'port'?: int,
 *     'path_private_key'?: string,
 *     'jump_host'?: string,
 *     'multiplexing_control_path'?: string,
 *     'multiplexing_control_persist'?: string,
 *     'enable_strict_check'?: bool,
 *     'password_authentication'?: bool,
 * } $sshOptions
 */
function ssh_download(
    string $sourcePath,
    string $destinationPath,
    string $host,
    string $user,
    array $sshOptions = [],
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?float $timeout = null,
): Process {
    $ssh = ssh_configuration($host, $user, $sshOptions);

    return run(
        $ssh->getDownloadCommand($sourcePath, $destinationPath),
        environment: [],
        tty: false,
        pty: false,
        timeout: $timeout,
        quiet: $quiet,
        allowFailure: $allowFailure,
        notify: $notify
    );
}

/**
 * @param array{
 *     'port'?: int,
 *     'path_private_key'?: string,
 *     'jump_host'?: string,
 *     'multiplexing_control_path'?: string,
 *     'multiplexing_control_persist'?: string,
 *     'enable_strict_check'?: bool,
 *     'password_authentication'?: bool,
 * } $sshOptions
 *
 * @internal
 */
function ssh_configuration(
    string $host,
    string $user,
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

function notify(string $message): void
{
    static $notifier;

    $notifier ??= NotifierFactory::create();

    $notification =
        (new Notification())
            ->setTitle('Castor')
            ->setBody($message)
    ;

    $notifier->send($notification);
}

/**
 * @param string|non-empty-array<string>                 $path
 * @param (callable(string, string) : (false|void|null)) $function
 */
function watch(string|array $path, callable $function, ?Context $context = null): void
{
    if (\is_array($path)) {
        $parallelCallbacks = [];

        foreach ($path as $p) {
            $parallelCallbacks[] = fn () => watch($p, $function, $context);
        }

        parallel(...$parallelCallbacks);

        return;
    }

    $context ??= GlobalHelper::getContext();

    $binary = match (true) {
        OsHelper::isMacOS() => match (php_uname('m')) {
            'arm64' => 'watcher-darwin-arm64',
            default => 'watcher-darwin-amd64',
        },
        OsHelper::isWindows() => 'watcher-windows.exe',
        default => 'watcher-linux',
    };

    $binaryPath = __DIR__ . '/../tools/watcher/bin/' . $binary;

    if (str_starts_with(__FILE__, 'phar:')) {
        static $tmpPath;

        if (null === $tmpPath) {
            $tmpPath = sys_get_temp_dir() . '/' . $binary;
            copy($binaryPath, $tmpPath);
            chmod($tmpPath, 0o755);
        }

        $binaryPath = $tmpPath;
    }

    $watchContext = $context->withTty(false)->withPty(false)->withTimeout(null);

    $command = [$binaryPath, $path];
    $buffer = '';

    run($command, callback: static function ($type, $bytes, $process) use ($function, &$buffer) {
        if (Process::OUT === $type) {
            $data = $buffer . $bytes;
            $lines = explode("\n", $data);

            while (!empty($lines)) {
                $line = trim($lines[0]);

                if ('' === $line) {
                    array_shift($lines);

                    continue;
                }

                try {
                    $eventLine = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $buffer = implode("\n", $lines);

                    break;
                }

                $result = $function($eventLine['name'], $eventLine['operation']);

                if (false === $result) {
                    $process->stop();
                }

                array_shift($lines);
            }
        } else {
            GlobalHelper::getSectionOutput()->writeProcessOutput($type, $bytes, $process);
        }
    }, context: $watchContext);
}

/**
 * @param array<string, mixed> $context
 *
 * @phpstan-param \Monolog\Level|\Psr\Log\LogLevel::* $level
 */
function log(string|\Stringable $message, mixed $level = 'info', array $context = []): void
{
    GlobalHelper::getLogger()->log($level, $message, $context);
}

function logger(): Logger
{
    return GlobalHelper::getLogger();
}

function app(): Application
{
    return GlobalHelper::getApplication();
}

function get_application(): Application
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'app');

    return app();
}

function input(): InputInterface
{
    return GlobalHelper::getInput();
}

function get_input(): InputInterface
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'input');

    return input();
}

function output(): OutputInterface
{
    return GlobalHelper::getOutput();
}

/**
 * @deprecated
 */
function get_output(): OutputInterface
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'output');

    return output();
}

function io(): SymfonyStyle
{
    return GlobalHelper::getSymfonyStyle();
}

function add_context(string $name, \Closure $callable, bool $default = false): void
{
    GlobalHelper::getContextRegistry()->addContext($name, $callable, $default);
}

function context(?string $name = null): Context
{
    return GlobalHelper::getContext($name);
}

function get_context(): Context
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'context');

    return context();
}

/**
 * @template TKey of key-of<ContextData>
 * @template TDefault
 *
 * @param TKey|string $key
 * @param TDefault    $default
 *
 * @phpstan-return ($key is TKey ? ContextData[TKey] : TDefault)
 */
function variable(string $key, mixed $default = null): mixed
{
    return GlobalHelper::getVariable($key, $default);
}

function task(): Command
{
    return GlobalHelper::getCommand();
}

function get_command(): Command
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'task');

    return task();
}

function fs(): Filesystem
{
    return GlobalHelper::getFilesystem();
}

function finder(): Finder
{
    return new Finder();
}

/**
 * @param string                                                                                      $key The key of the item to retrieve from the cache
 * @param (callable(CacheItemInterface,bool):T)|(callable(ItemInterface,bool):T)|CallbackInterface<T> $or  Use this callback to compute the value
 *
 * @return T
 *
 * @see CacheInterface::get()
 *
 * @template T
 */
function cache(string $key, callable $or): mixed
{
    $key = sprintf(
        '%s-%s',
        hash('xxh128', PathHelper::getRoot()),
        $key,
    );

    return GlobalHelper::getCache()->get($key, $or);
}

function get_cache(): CacheItemPoolInterface&CacheInterface
{
    return GlobalHelper::getCache();
}

/**
 * @param array<string, mixed> $options
 *
 * @see HttpClientInterface::OPTIONS_DEFAULTS
 */
function request(string $method, string $url, array $options = []): ResponseInterface
{
    return http_client()->request($method, $url, $options);
}

function http_client(): HttpClientInterface
{
    return GlobalHelper::getHttpClient();
}

function import(string $path): void
{
    if (!file_exists($path)) {
        throw fix_exception(new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $path)));
    }

    if (is_file($path)) {
        castor_require($path);
    }

    if (is_dir($path)) {
        $files = Finder::create()
            ->files()
            ->name('*.php')
            ->in($path)
        ;

        foreach ($files as $file) {
            castor_require($file->getPathname());
        }
    }
}

/**
 * Remove the last frame (the call to run() to display a nice message to the end user.
 *
 * @internal
 */
function fix_exception(\Exception $exception): \Exception
{
    $lastFrame = $exception->getTrace()[0];
    foreach (['file', 'line'] as $key) {
        if (!\array_key_exists($key, $lastFrame)) {
            continue;
        }
        $r = new \ReflectionProperty(\Exception::class, $key);
        $r->setAccessible(true);
        $r->setValue($exception, $lastFrame[$key]);
    }

    return $exception;
}

/**
 * @return array<string, mixed>
 */
function load_dot_env(?string $path = null): array
{
    $path ??= PathHelper::getRoot() . '/.env';

    $dotenv = new Dotenv();
    $dotenv->loadEnv($path);
    unset($_ENV['SYMFONY_DOTENV_VARS']);

    return $_ENV;
}

/**
 * @template T
 *
 * @param (callable(Context) :T)                     $callback
 * @param array<string, string|\Stringable|int>|null $data
 * @param array<string, string|\Stringable|int>|null $environment
 */
function with(
    callable $callback,
    ?array $data = null,
    ?array $environment = null,
    ?string $path = null,
    ?bool $tty = null,
    ?bool $pty = null,
    ?float $timeout = null,
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    Context|string|null $context = null,
): mixed {
    $contextRegistry = GlobalHelper::getContextRegistry();
    $initialContext = $contextRegistry->getCurrentContext();
    $context ??= $initialContext;

    if (\is_string($context)) {
        $context = context($context);
    }

    if (null !== $data) {
        $context = $context->withData($data);
    }

    if (null !== $environment) {
        $context = $context->withEnvironment($environment);
    }

    if ($path) {
        $context = $context->withPath($path);
    }

    if (null !== $tty) {
        $context = $context->withTty($tty);
    }

    if (null !== $pty) {
        $context = $context->withPty($pty);
    }

    if (null !== $timeout) {
        $context = $context->withTimeout($timeout);
    }

    if (null !== $quiet) {
        $context = $context->withQuiet($quiet);
    }

    if (null !== $allowFailure) {
        $context = $context->withAllowFailure($allowFailure);
    }

    if (null !== $notify) {
        $context = $context->withNotify($notify);
    }

    $contextRegistry->setCurrentContext($context);

    try {
        return $callback($context);
    } finally {
        $contextRegistry->setCurrentContext($initialContext);
    }
}

/**
 * @see https://www.php.net/manual/en/function.hash-algos.php
 */
function hasher(string $algo = 'xxh128'): HasherHelper
{
    return new HasherHelper(GlobalHelper::getApplication(), GlobalHelper::getLogger(), $algo);
}

function fingerprint_exists(string $fingerprint): bool
{
    return GlobalHelper::getApplication()->fingerprintHelper->verifyFingerprintFromHash($fingerprint);
}

function fingerprint_save(string $fingerprint): void
{
    GlobalHelper::getApplication()->fingerprintHelper->postProcessFingerprintForHash($fingerprint);
}

function fingerprint(callable $callback, string $fingerprint, bool $force = false): void
{
    if (!fingerprint_exists($fingerprint) || $force) {
        try {
            $callback();
            fingerprint_save($fingerprint);
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}

/**
 * @throws TimeoutReachedException
 * @throws ExitedBeforeTimeoutException
 */
function wait_for(
    callable $callback,
    int $timeout = 10,
    bool $quiet = false,
    int $intervalMs = 100,
    string $message = 'Waiting for callback to be available...',
): void {
    GlobalHelper::getApplication()->waitForHelper->waitFor(
        io: io(),
        callback: $callback,
        timeout: $timeout,
        quiet: $quiet,
        intervalMs: $intervalMs,
        message: $message,
    );
}

/**
 * @throws TimeoutReachedException
 * @throws ExitedBeforeTimeoutException
 */
function wait_for_port(
    int $port,
    string $host = '127.0.0.1',
    int $timeout = 10,
    bool $quiet = false,
    int $intervalMs = 100,
    ?string $message = null,
): void {
    GlobalHelper::getApplication()->waitForHelper->waitForPort(
        io: io(),
        port: $port,
        host: $host,
        timeout: $timeout,
        quiet: $quiet,
        intervalMs: $intervalMs,
        message: $message,
    );
}

/**
 * @throws TimeoutReachedException
 * @throws ExitedBeforeTimeoutException
 */
function wait_for_url(
    string $url,
    int $timeout = 10,
    bool $quiet = false,
    int $intervalMs = 100,
    ?string $message = null,
): void {
    GlobalHelper::getApplication()->waitForHelper->waitForUrl(
        io: io(),
        url: $url,
        timeout: $timeout,
        quiet: $quiet,
        intervalMs: $intervalMs,
        message: $message,
    );
}

/**
 * @throws TimeoutReachedException
 * @throws ExitedBeforeTimeoutException
 */
function wait_for_http_status(
    string $url,
    int $status = 200,
    int $timeout = 10,
    bool $quiet = false,
    int $intervalMs = 100,
    ?string $message = null,
): void {
    GlobalHelper::getApplication()->waitForHelper->waitForHttpStatus(
        io: io(),
        url: $url,
        status: $status,
        timeout: $timeout,
        quiet: $quiet,
        intervalMs: $intervalMs,
        message: $message,
    );
}

/**
 * @throws TimeoutReachedException
 * @throws ExitedBeforeTimeoutException
 */
function wait_for_http_response(
    string $url,
    ?callable $responseChecker = null,
    int $timeout = 10,
    bool $quiet = false,
    int $intervalMs = 100,
    ?string $message = null,
): void {
    GlobalHelper::getApplication()->waitForHelper->waitForHttpResponse(
        io: io(),
        url: $url,
        responseChecker: $responseChecker,
        timeout: $timeout,
        quiet: $quiet,
        intervalMs: $intervalMs,
        message: $message,
    );
}

/**
 * @throws TimeoutReachedException
 */
function wait_for_docker_container(
    string $containerName,
    int $timeout = 10,
    bool $quiet = false,
    int $intervalMs = 100,
    ?string $message = null,
    ?callable $containerChecker = null,
): void {
    GlobalHelper::getApplication()->waitForHelper->waitForDockerContainer(
        io: io(),
        containerName: $containerName,
        timeout: $timeout,
        quiet: $quiet,
        intervalMs: $intervalMs,
        message: $message,
        containerChecker: $containerChecker,
    );
}

function guard_min_version(string $minVersion): void
{
    $currentVersion = GlobalHelper::getApplication()->getVersion();

    $minVersion = u($minVersion)->ensureStart('v')->toString();
    if (version_compare($currentVersion, $minVersion, '<')) {
        throw new MinimumVersionRequirementNotMetException($minVersion, $currentVersion);
    }
}
