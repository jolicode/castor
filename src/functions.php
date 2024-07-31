<?php

namespace Castor;

use Castor\Attribute\AsContextGenerator;
use Castor\Console\Application;
use Castor\Exception\ExecutableNotFoundException;
use Castor\Exception\MinimumVersionRequirementNotMetException;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;
use Castor\Helper\HasherHelper;
use Castor\Helper\PathHelper;
use Castor\Import\Mount;
use JoliCode\PhpOsHelper\OsHelper;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Castor\Internal\fix_exception;
use function Symfony\Component\String\u;

/**
 * @return array<mixed>
 */
function parallel(callable ...$callbacks): array
{
    return Container::get()->parallelRunner->parallel(...$callbacks);
}

/**
 * @param string|array<string|\Stringable|int>           $command
 * @param array<string, string|\Stringable|int>|null     $environment
 * @param (callable(string, string, Process) :void)|null $callback
 */
function run(
    string|array $command,
    ?array $environment = null,
    ?string $workingDirectory = null,
    ?bool $tty = null,
    ?bool $pty = null,
    ?float $timeout = null,
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?callable $callback = null,
    ?Context $context = null,
    ?string $path = null,
): Process {
    if ($workingDirectory && $path) {
        throw new \LogicException('You cannot use both the "path" and "workingDirectory" arguments at the same time.');
    }
    if ($path) {
        trigger_deprecation('castor', '0.15', 'The "path" argument is deprecated, use "workingDirectory" instead.');

        $workingDirectory = $path;
    }

    return Container::get()->processRunner->run(
        $command,
        $environment,
        $workingDirectory,
        $tty,
        $pty,
        $timeout,
        $quiet,
        $allowFailure,
        $notify,
        $callback,
        $context,
    );
}

/**
 * @param string|array<string|\Stringable|int>       $command
 * @param array<string, string|\Stringable|int>|null $environment
 */
function capture(
    string|array $command,
    ?array $environment = null,
    ?string $workingDirectory = null,
    ?float $timeout = null,
    ?bool $allowFailure = null,
    ?string $onFailure = null,
    ?Context $context = null,
    ?string $path = null,
): string {
    if ($workingDirectory && $path) {
        throw new \LogicException('You cannot use both the "path" and "workingDirectory" arguments at the same time.');
    }
    if ($path) {
        trigger_deprecation('castor', '0.15', 'The "path" argument is deprecated, use "workingDirectory" instead.');

        $workingDirectory = $path;
    }

    return Container::get()->processRunner->capture(
        $command,
        $environment,
        $workingDirectory,
        $timeout,
        $allowFailure,
        $onFailure,
        $context,
    );
}

/**
 * @param string|array<string|\Stringable|int>       $command
 * @param array<string, string|\Stringable|int>|null $environment
 */
function exit_code(
    string|array $command,
    ?array $environment = null,
    ?string $workingDirectory = null,
    ?float $timeout = null,
    ?bool $quiet = null,
    ?Context $context = null,
    ?string $path = null,
): int {
    if ($workingDirectory && $path) {
        throw new \LogicException('You cannot use both the "path" and "workingDirectory" arguments at the same time.');
    }
    if ($path) {
        trigger_deprecation('castor', '0.15', 'The "path" argument is deprecated, use "workingDirectory" instead.');

        $workingDirectory = $path;
    }

    return Container::get()->processRunner->exitCode(
        $command,
        $environment,
        $workingDirectory,
        $timeout,
        $quiet,
        $context,
    );
}

/**
 * @deprecated Since castor/castor 0.8. Use Castor\exit_code() instead
 */
function get_exit_code(...$args): int
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'exit_code');

    return exit_code(...$args);
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
 */
function ssh_run(
    string $command,
    string $host,
    ?string $user = null,
    array $sshOptions = [],
    ?string $path = null,
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?float $timeout = null,
    ?callable $callback = null,
): Process {
    return Container::get()->sshRunner->execute($command, $path, $host, $user, $sshOptions, $quiet, $allowFailure, $notify, $timeout, $callback);
}

/**
 * @deprecated Since castor/castor 0.10. Use Castor\ssh_run() instead
 */
function ssh(...$args): Process
{
    trigger_deprecation('jolicode/castor', '0.10', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'ssh_run');

    return ssh_run(...$args);
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
 */
function ssh_upload(
    string $sourcePath,
    string $destinationPath,
    string $host,
    ?string $user = null,
    array $sshOptions = [],
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?float $timeout = null,
): Process {
    return Container::get()->sshRunner->upload($sourcePath, $destinationPath, $host, $user, $sshOptions, $quiet, $allowFailure, $notify, $timeout);
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
 */
function ssh_download(
    string $sourcePath,
    string $destinationPath,
    string $host,
    ?string $user = null,
    array $sshOptions = [],
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?float $timeout = null,
): Process {
    return Container::get()->sshRunner->download($sourcePath, $destinationPath, $host, $user, $sshOptions, $quiet, $allowFailure, $notify, $timeout);
}

function notify(string $message, ?string $title = null): void
{
    Container::get()->notifier->send($message, $title);
}

/**
 * @param string|non-empty-array<string>                 $path
 * @param (callable(string, string) : (false|void|null)) $function
 */
function watch(string|array $path, callable $function, ?Context $context = null): void
{
    Container::get()->watchRunner->watch($path, $function, $context);
}

/**
 * @param array<string, mixed> $context
 *
 * @phpstan-param \Monolog\Level|\Psr\Log\LogLevel::* $level
 */
function log(string|\Stringable $message, mixed $level = 'info', array $context = []): void
{
    Container::get()->logger->log($level, $message, $context);
}

function logger(): LoggerInterface
{
    return Container::get()->logger;
}

function app(): Application
{
    return Container::get()->application;
}

/**
 * @deprecated Since castor/castor 0.8. Use Castor\app() instead
 */
function get_application(): Application
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'app');

    return app();
}

function input(): InputInterface
{
    return Container::get()->input;
}

/**
 * @deprecated Since castor/castor 0.8. Use Castor\input() instead
 */
function get_input(): InputInterface
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'input');

    return input();
}

function output(): OutputInterface
{
    return Container::get()->output;
}

/**
 * @deprecated Since castor/castor 0.8. Use Castor\output() instead
 */
function get_output(): OutputInterface
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'output');

    return output();
}

function io(): SymfonyStyle
{
    return Container::get()->symfonyStyle;
}

/**
 * @deprecated Since castor/castor 0.13. Use "Castor\Attributes\AsContextGenerator()" instead.
 */
function add_context(string $name, \Closure $callable, bool $default = false): void
{
    trigger_deprecation('jolicode/castor', '0.13', 'The "%s()" function is deprecated, use "Castor\Attributes\%s()" instead.', __FUNCTION__, AsContextGenerator::class);

    Container::get()->contextRegistry->addContext($name, $callable, $default);
}

function context(?string $name = null): Context
{
    return Container::get()->getContext($name);
}

/**
 * @deprecated Since castor/castor 0.8. Use Castor\context() instead
 */
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
    return Container::get()->getVariable($key, $default);
}

/**
 * @return ($allowNull is true ? ?Command : Command)
 */
function task(bool $allowNull = false): ?Command
{
    return Container::get()->getCommand($allowNull);
}

/**
 * @deprecated Since castor/castor 0.8. Use Castor\task() instead
 */
function get_command(): Command
{
    trigger_deprecation('jolicode/castor', '0.8', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'task');

    return task();
}

function fs(): Filesystem
{
    return Container::get()->fs;
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
    $key = \sprintf(
        '%s-%s',
        hash('xxh128', PathHelper::getRoot()),
        $key,
    );

    return Container::get()->cache->get($key, $or);
}

function get_cache(): CacheItemPoolInterface&CacheInterface
{
    return Container::get()->cache;
}

/**
 * @deprecated Since castor/castor 0.16. Use Castor\http_request() instead
 */
function request(...$args): ResponseInterface
{
    trigger_deprecation('jolicode/castor', '0.16', 'The "%s()" function is deprecated, use "Castor\%s()" instead.', __FUNCTION__, 'http_request');

    return http_request(...$args);
}

/**
 * @param array<string, mixed> $options default values at {@see HttpClientInterface::OPTIONS_DEFAULTS}
 */
function http_request(string $method, string $url, array $options = []): ResponseInterface
{
    return Container::get()->httpClient->request($method, $url, $options);
}

/**
 * @param array<string, mixed> $options default values at {@see HttpClientInterface::OPTIONS_DEFAULTS}
 */
function http_download(string $url, ?string $filePath = null, string $method = 'GET', array $options = [], bool $stream = true): ResponseInterface
{
    return Container::get()->httpDownloader->download($url, $filePath, $method, $options, $stream);
}

function http_client(): HttpClientInterface
{
    return Container::get()->httpClient;
}

/**
 * @param ?array{
 *     url?: string,
 *     type?: "git" | "svn",
 *     reference?: string,
 * } $source
 */
function import(string $path, ?string $file = null, ?string $version = null, ?string $vcs = null, ?array $source = null): void
{
    if (null !== $version || null !== $vcs || null !== $source) {
        @trigger_deprecation('castor/castor', '0.16.0', 'The "version", "vcs" and "source" arguments are deprecated, use the `castor.composer.json` file instead.');
    }

    Container::get()->importer->import($path, $file);
}

function mount(string $path, ?string $namespacePrefix = null): void
{
    if (!is_dir($path)) {
        throw fix_exception(new \InvalidArgumentException(\sprintf('The directory "%s" does not exist.', $path)));
    }

    Container::get()->kernel->addMount(new Mount($path, namespacePrefix: $namespacePrefix));
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
    ?string $workingDirectory = null,
    ?bool $tty = null,
    ?bool $pty = null,
    ?float $timeout = null,
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    Context|string|null $context = null,
    ?string $path = null,
): mixed {
    $contextRegistry = Container::get()->contextRegistry;

    $initialContext = null;
    if ($contextRegistry->hasCurrentContext()) {
        $initialContext = $contextRegistry->getCurrentContext();
    }

    $context ??= new Context();
    if (\is_string($context)) {
        $context = $contextRegistry->get($context);
    }

    if (null !== $data) {
        $context = $context->withData($data);
    }

    if (null !== $environment) {
        $context = $context->withEnvironment($environment);
    }

    if ($workingDirectory) {
        $context = $context->withWorkingDirectory($workingDirectory);
        if ($path) {
            throw new \LogicException('You cannot use both the "path" and "workingDirectory" arguments at the same time.');
        }
    }
    if ($path) {
        trigger_deprecation('castor', '0.15', 'The "path" argument is deprecated, use "workingDirectory" instead.');

        $context = $context->withWorkingDirectory($path);
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
        if ($initialContext) {
            $contextRegistry->setCurrentContext($initialContext);
        }
    }
}

/**
 * @see https://www.php.net/manual/en/function.hash-algos.php
 */
function hasher(string $algo = 'xxh128'): HasherHelper
{
    return new HasherHelper(
        Container::get()->getCommand(),
        Container::get()->input,
        Container::get()->logger,
        $algo,
    );
}

function fingerprint_exists(string $fingerprint): bool
{
    return Container::get()->fingerprintHelper->verifyFingerprintFromHash($fingerprint);
}

function fingerprint_save(string $fingerprint): void
{
    Container::get()->fingerprintHelper->postProcessFingerprintForHash($fingerprint);
}

function fingerprint(callable $callback, string $fingerprint, bool $force = false): bool
{
    if ($force || !fingerprint_exists($fingerprint)) {
        $callback();
        fingerprint_save($fingerprint);

        return true;
    }

    return false;
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
    Container::get()->waiter->waitFor(
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
    Container::get()->waiter->waitForPort(
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
    Container::get()->waiter->waitForUrl(
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
    Container::get()->waiter->waitForHttpStatus(
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
    Container::get()->waiter->waitForHttpResponse(
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
    Container::get()->waiter->waitForDockerContainer(
        io: io(),
        containerName: $containerName,
        timeout: $timeout,
        quiet: $quiet,
        intervalMs: $intervalMs,
        message: $message,
        containerChecker: $containerChecker,
    );
}

/**
 * @see Yaml::parse()
 */
function yaml_parse(string $content, int $flags = 0): mixed
{
    return Yaml::parse($content, $flags);
}

/**
 * @see Yaml::dump()
 */
function yaml_dump(mixed $input, int $inline = 2, int $indent = 4, int $flags = 0): string
{
    return Yaml::dump($input, $inline, $indent, $flags);
}

function guard_min_version(string $minVersion): void
{
    $currentVersion = Container::get()->application->getVersion();

    $minVersion = u($minVersion)->ensureStart('v')->toString();
    if (version_compare($currentVersion, $minVersion, '<')) {
        throw fix_exception(new MinimumVersionRequirementNotMetException($minVersion, $currentVersion));
    }
}

function open(string ...$urls): void
{
    $command = match (true) {
        OsHelper::isMacOS() => 'open',
        OsHelper::isWindows() => 'start',
        default => 'xdg-open',
    };

    if (null === (new ExecutableFinder())->find($command)) {
        throw new ExecutableNotFoundException($command);
    }

    $parallelCallbacks = [];

    foreach ($urls as $url) {
        $parallelCallbacks[] = fn () => run([$command, $url], quiet: true);
    }

    parallel(...$parallelCallbacks);
}

/**
 * @param array<string|\Stringable>                      $arguments
 * @param array<string, string|\Stringable|int>|null     $environment
 * @param (callable(string, string, Process) :void)|null $callback
 */
function run_phar(
    string $pharPath,
    array $arguments = [],
    ?array $environment = null,
    ?string $workingDirectory = null,
    ?bool $tty = null,
    ?bool $pty = null,
    ?float $timeout = null,
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?callable $callback = null,
    ?Context $context = null,
    ?string $path = null): Process
{
    // get program path
    $castorPath = $_SERVER['argv'][0];

    return run([$castorPath, 'run-phar', $pharPath, ...$arguments], $environment, $workingDirectory, $tty, $pty, $timeout, $quiet, $allowFailure, $notify, $callback, $context, $path);
}
