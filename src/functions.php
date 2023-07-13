<?php

namespace Castor;

use Castor\Console\Application;
use Joli\JoliNotif\Notification;
use Joli\JoliNotif\NotifierFactory;
use Joli\JoliNotif\Util\OsHelper;
use Monolog\Level;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LogLevel;
use Spatie\Ssh\Ssh;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @return array<mixed>
 */
function parallel(callable ...$callbacks): array
{
    $fibers = [];
    foreach ($callbacks as $callback) {
        $fiber = new \Fiber($callback);
        $fiber->start();

        $fibers[] = $fiber;
    }

    $isRunning = true;

    while ($isRunning) {
        $isRunning = false;

        foreach ($fibers as $fiber) {
            $isRunning = $isRunning || !$fiber->isTerminated();

            if (!$fiber->isTerminated() && $fiber->isSuspended()) {
                $fiber->resume();
            }
        }

        if (\Fiber::getCurrent()) {
            \Fiber::suspend();
            usleep(1_000);
        }
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
    array $environment = null,
    string $path = null,
    bool $tty = null,
    bool $pty = null,
    float $timeout = null,
    bool $quiet = null,
    bool $allowFailure = null,
    bool $notify = null,
    callable $callback = null,
    Context $context = null,
): Process {
    $context ??= GlobalHelper::getInitialContext();

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

    if ($context->tty) {
        $process->setTty(true);
        $process->setInput(\STDIN);
    } elseif ($context->pty) {
        $process->setPty(true);
        $process->setInput(\STDIN);
    }

    if (!$context->quiet && !$callback) {
        $callback = static function ($type, $bytes) {
            if (Process::OUT === $type) {
                fwrite(\STDOUT, $bytes);
            } else {
                fwrite(\STDERR, $bytes);
            }
        };
    }

    log(sprintf('Running command: "%s".', $process->getCommandLine()), 'info', [
        'process' => $process,
    ]);

    $process->start(function ($type, $bytes) use ($callback, $process) {
        if ($callback) {
            $callback($type, $bytes, $process);
        }
    });

    if (\Fiber::getCurrent()) {
        while ($process->isRunning()) {
            \Fiber::suspend();
            usleep(1_000);
        }
    }

    $exitCode = $process->wait();

    if ($context->notify) {
        notify(sprintf('The command "%s" has been finished %s.', $process->getCommandLine(), 0 === $exitCode ? 'successfully' : 'with an error'));
    }

    if (0 !== $exitCode) {
        log(sprintf('Command finished with and error (exit code=%d).', $process->getExitCode()), 'notice');
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
    array $environment = null,
    string $path = null,
    float $timeout = null,
    bool $allowFailure = null,
    string $onFailure = null,
    Context $context = null,
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
function get_exit_code(
    string|array $command,
    array $environment = null,
    string $path = null,
    float $timeout = null,
    bool $quiet = null,
    Context $context = null,
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
function ssh(
    string $command,
    string $host,
    string $user,
    array $sshOptions = [],
    string $path = null,
    bool $quiet = null,
    bool $allowFailure = null,
    bool $notify = null,
    float $timeout = null,
): Process {
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
        $sshOptions['password_authentication'] ? $ssh->enablePasswordAuthentication() : $ssh->disableStrictHostKeyChecking();
    }
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
function watch(string|array $path, callable $function, Context $context = null): void
{
    if (\is_array($path)) {
        $parallelCallbacks = [];

        foreach ($path as $p) {
            $parallelCallbacks[] = fn () => watch($p, $function, $context);
        }

        parallel(...$parallelCallbacks);

        return;
    }

    $context ??= GlobalHelper::getInitialContext();

    $binary = match (true) {
        OSHelper::isMacOS() => 'watcher-darwin',
        OSHelper::isWindows() => 'watcher-windows.exe',
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
            fwrite(\STDERR, "ERROR: {$type} : " . $bytes);
        }
    }, context: $watchContext);
}

/**
 * @param array<string, mixed> $context
 *
 * @phpstan-param Level|LogLevel::* $level
 */
function log(string|\Stringable $message, mixed $level = 'info', array $context = []): void
{
    GlobalHelper::getLogger()->log($level, $message, $context);
}

function get_application(): Application
{
    return GlobalHelper::getApplication();
}

function get_input(): InputInterface
{
    return GlobalHelper::getInput();
}

function get_output(): OutputInterface
{
    return GlobalHelper::getOutput();
}

function io(): SymfonyStyle
{
    return GlobalHelper::getSymfonyStyle();
}

function get_logger(): Logger
{
    return GlobalHelper::getLogger();
}

function add_context(string $name, \Closure $callable, bool $default = false): void
{
    GlobalHelper::getContextRegistry()->addContext($name, $callable, $default);
}

function get_context(): Context
{
    return GlobalHelper::getInitialContext();
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

function get_command(): Command
{
    return GlobalHelper::getCommand();
}

function fs(): Filesystem
{
    return GlobalHelper::getFilesystem();
}

function finder(): Finder
{
    return new Finder();
}

function cache(string $key, callable $callback): mixed
{
    $key = sprintf(
        '%s-%s',
        hash('xxh128', PathHelper::getRoot()),
        $key,
    );

    return GlobalHelper::getCache()->get($key, $callback);
}

function get_cache(): CacheItemPoolInterface&CacheInterface
{
    return GlobalHelper::getCache();
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

// Remove the last frame (the call to run() to display a nice message to the end user
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
function load_dot_env(string $path = null): array
{
    $path ??= PathHelper::getRoot() . '/.env';

    $dotenv = new Dotenv();
    $dotenv->loadEnv($path);
    unset($_ENV['SYMFONY_DOTENV_VARS']);

    return $_ENV;
}
