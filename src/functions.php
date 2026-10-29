<?php

namespace Castor;

use Castor\CommandBuilder\CommandBuilderInterface;
use Castor\Console\Application;
use Castor\Exception\ExecutableNotFoundException;
use Castor\Exception\LlmException;
use Castor\Exception\MinimumVersionRequirementNotMetException;
use Castor\Exception\ProblemException;
use Castor\Exception\WaitFor\DockerContainerStateException;
use Castor\Exception\WaitFor\ExitedBeforeTimeoutException;
use Castor\Exception\WaitFor\TimeoutReachedException;
use Castor\Helper\CompressionMethod;
use Castor\Helper\HasherHelper;
use Castor\Helper\PathHelper;
use JoliCode\PhpOsHelper\OsHelper;
use Monolog\Level;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Symfony\Component\String\u;

/**
 * Runs the functions in parallel, and returns their results.
 *
 * Named, like parallel(lint: …, test: …), each function becomes a job: its
 * output is organized, and its result is keyed by its name.
 *
 * @return array<array-key, mixed>
 */
function parallel(callable ...$callbacks): array
{
    return Container::get()->parallelRunner->parallel(...$callbacks);
}

/**
 * Runs the command, in the current context unless one is given, and returns
 * its process once it is finished. Its output is displayed, unless the context
 * is quiet or a callback receives it.
 *
 * A string is run by a shell, an array is not: its arguments need no escaping.
 *
 * @param string|array<string|\Stringable|int>|CommandBuilderInterface $command
 * @param (callable(string, string, Process) :void)|null               $callback
 *
 * @throws ProcessFailedException When the command fails, unless the context allows failures
 */
function run(
    string|array|CommandBuilderInterface $command,
    ?Context $context = null,
    ?callable $callback = null,
): Process {
    return Container::get()
        ->processRunner
        ->run(
            $command,
            $context,
            $callback,
        )
    ;
}

/**
 * Runs the command without displaying its output, and returns its standard
 * output, trimmed.
 *
 * @param string|array<string|\Stringable|int> $command
 * @param ?string                              $onFailure Returned when the command fails, instead of throwing
 *
 * @throws ProcessFailedException When the command fails, unless $onFailure is given or the context allows failures
 */
function capture(
    string|array $command,
    ?Context $context = null,
    ?string $onFailure = null,
): string {
    return Container::get()
        ->processRunner
        ->capture(
            $command,
            $context,
            $onFailure,
        )
    ;
}

/**
 * Runs the command, and returns its exit code: it does not throw when the
 * command fails.
 *
 * @param string|array<string|\Stringable|int> $command
 */
function exit_code(
    string|array $command,
    ?Context $context = null,
): int {
    return Container::get()
        ->processRunner
        ->exitCode(
            $command,
            $context,
        )
    ;
}

/**
 * Asks a question to a LLM, through an AI CLI installed on the machine (Claude
 * Code, Codex, OpenCode, Gemini CLI, ...) run in its non-interactive and
 * read-only mode, and returns its answer.
 *
 * @param string|list<string>|null $cli The name of a known CLI with its options ("claude", "codex --model
 *                                      gpt-5.5", ...), a custom command which receives the prompt on its
 *                                      standard input, or "choose" to ask which one to use. By default, the
 *                                      CASTOR_LLM environment variable, then the configuration made with
 *                                      "castor:llm:configure", decide, or Castor asks
 *
 * @throws LlmException When the CLI cannot be chosen, when the call is refused, or when the CLI could not answer
 */
function llm_ask(string $prompt, string|array|null $cli = null, ?Context $context = null): string
{
    return Container::get()
        ->llmRunner
        ->ask($prompt, $cli, $context)
    ;
}

/**
 * Lets a LLM work on the project: the AI CLI is run in its non-interactive
 * mode, allowed to modify the files and to run commands, and its final answer
 * is returned. Check what it did before trusting it.
 *
 * @param string|list<string>|null $cli Same as llm_ask(). The "llm" CLI only answers questions: naming it
 *                                      here throws, and Castor does not propose it when it asks
 *
 * @throws LlmException When the CLI cannot be chosen or cannot modify the project, when the call is refused, or when the CLI failed
 */
function llm_run(string $prompt, string|array|null $cli = null, ?Context $context = null): string
{
    return Container::get()
        ->llmRunner
        ->run($prompt, $cli, $context)
    ;
}

/**
 * Displays the title, then runs the check: when it returns false, the task
 * stops with the failure message.
 *
 * @param callable():bool $check
 *
 * @throws ProblemException When the check returns false
 */
function check(string $title, string $failureMessage, callable $check): void
{
    io()->write($title);

    if (!$check()) {
        io()->writeln(' ❌');

        throw new ProblemException($failureMessage);
    }

    io()->writeln(' ✅');
}

/**
 * Runs the command on the host, through SSH.
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
 * @param ?string $path The directory to run the command in, on the host
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
 * Copies a local file to the host, through SCP.
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
 * Copies a file of the host to the local machine, through SCP.
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
    ?string $user = null,
    array $sshOptions = [],
    ?bool $quiet = null,
    ?bool $allowFailure = null,
    ?bool $notify = null,
    ?float $timeout = null,
): Process {
    return Container::get()->sshRunner->download($sourcePath, $destinationPath, $host, $user, $sshOptions, $quiet, $allowFailure, $notify, $timeout);
}

/**
 * Sends a desktop notification, unless the context disables them.
 */
function notify(string $message, ?string $title = null): void
{
    Container::get()->notifier->send($message, $title);
}

/**
 * Calls the function with the absolute path and the action ("create", "write",
 * "rename" or "remove") of each change in the path, until it returns false.
 *
 * @param string|non-empty-array<string>                 $path     Suffix it with "/..." to watch the subdirectories too
 * @param (callable(string, string) : (false|void|null)) $function
 */
function watch(string|array $path, callable $function, ?Context $context = null): void
{
    Container::get()->watchRunner->watch($path, $function, $context);
}

/**
 * Logs the message. By default, only the warnings and above are displayed: "-v"
 * displays the notices too, "-vv" the infos, and "-vvv" everything.
 *
 * @param array<string, mixed> $context
 *
 * @phpstan-param Level|\Psr\Log\LogLevel::* $level
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
 * Dispatch an event through Castor's event dispatcher.
 *
 * @template T of object
 *
 * @param T $event
 *
 * @return T
 */
function dispatch(object $event, ?string $eventName = null): object
{
    return Container::get()->eventDispatcher->dispatch($event, $eventName);
}

function event_dispatcher(): EventDispatcherInterface
{
    return Container::get()->eventDispatcher;
}

function input(): InputInterface
{
    return Container::get()->input;
}

function output(): OutputInterface
{
    // Inside a job of parallel(), the output is captured to be organized
    $job = Container::get()->jobRegistry->getCurrent();

    return $job ? $job->output : Container::get()->output;
}

function io(): SymfonyStyle
{
    // Inside a job of parallel(), the output is captured to be organized
    return Container::get()->jobRegistry->getCurrentIo() ?? Container::get()->symfonyStyle;
}

/**
 * Returns the current context, or the context with this name.
 */
function context(?string $name = null): Context
{
    return Container::get()->getContext($name);
}

/**
 * Returns the value of the key in the data of the current context, or the
 * default value when it is not set.
 *
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
 * Returns the command of the task being run.
 *
 * @return ($allowNull is true ? ?Command : Command)
 */
function task(bool $allowNull = false): ?Command
{
    return Container::get()->getCommand($allowNull);
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
 * Returns the value of the key from the cache of the project, which is kept
 * between the runs, or computes it with $or when it is missing.
 *
 * @param string                                                                                      $key   The key of the item to retrieve from the cache
 * @param (callable(CacheItemInterface,bool):T)|(callable(ItemInterface,bool):T)|CallbackInterface<T> $or    Use this callback to compute the value
 * @param bool                                                                                        $force Force the value to be recomputed
 *
 * @return T
 *
 * @see CacheInterface::get()
 *
 * @template T
 */
function cache(string $key, callable $or, bool $force = false): mixed
{
    $key = \sprintf(
        '%s-%s',
        hash('xxh128', PathHelper::getRoot()),
        $key,
    );

    if ($force) {
        Container::get()->cache->delete($key);
    }

    return Container::get()->cache->get($key, $or);
}

function get_cache(): CacheItemPoolInterface&CacheInterface
{
    return Container::get()->cache;
}

/**
 * @param array<string, mixed> $options default values at {@see HttpClientInterface::OPTIONS_DEFAULTS}
 */
function http_request(string $method, string $url, array $options = []): ResponseInterface
{
    return Container::get()->httpClient->request($method, $url, $options);
}

/**
 * Downloads the URL to the file, and logs the progress.
 *
 * @param ?string              $filePath Defaults to a file of the project root, named after the response or the URL
 * @param array<string, mixed> $options  default values at {@see HttpClientInterface::OPTIONS_DEFAULTS}
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
 * Loads the tasks, contexts and listeners of a PHP file, of the PHP files of a
 * directory, or of a Composer package like "composer://vendor/package", required
 * in castor.composer.json: the ones of its castor.php and of the files it
 * autoloads. Unlike mount(), it does not change the working directory of the
 * tasks.
 *
 * Classes are not imported: they are autoloaded, from the "autoload" section of
 * castor.composer.json.
 *
 * @param ?string $file The file to load from the package. Defaults to its castor.php
 */
function import(string $path, ?string $file = null): void
{
    Container::get()->importer->import($path, $file);
}

/**
 * Loads the tasks of another Castor application, a directory with its own
 * castor.php or a Composer package like "composer://vendor/package", the tasks
 * of the files it autoloads included: they run in its directory.
 *
 * @param ?string $namespacePrefix Prepended to the namespace of its tasks, like "app" for "app:build"
 * @param ?string $file            The file to load instead of its castor.php
 */
function mount(string $path, ?string $namespacePrefix = null, ?string $file = null): void
{
    Container::get()->mounter->mount($path, $namespacePrefix, $file);
}

/**
 * Loads the .env file, and the .env.local, .env.<APP_ENV>... files next to
 * it, like Symfony does, and returns $_ENV. The commands run afterwards get
 * these variables.
 *
 * @param ?string $path Defaults to the .env file of the project root
 *
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
 * Runs the callback with a context, and returns its result: the commands it
 * runs use this context, unless they get another one. The context is $context,
 * or a new Context by default (not the current one), modified by the other
 * arguments.
 *
 * @template T
 *
 * @param (callable(Context) :T)                       $callback
 * @param array<string, string|\Stringable|false>|null $data
 * @param array<string, string|\Stringable|false>|null $environment
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
 * Returns a hasher, to compute a fingerprint for fingerprint() for instance.
 *
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

/**
 * Whether the fingerprint is the one saved for the id by fingerprint_save().
 *
 * @param bool $global Shares the fingerprint between all the projects
 */
function fingerprint_exists(string $id, string $fingerprint, bool $global = false): bool
{
    return Container::get()->fingerprintHelper->verifyFingerprintFromHash($id, $fingerprint, $global);
}

/**
 * Saves the fingerprint for the id.
 *
 * @param bool $global Shares the fingerprint between all the projects
 */
function fingerprint_save(string $id, string $fingerprint, bool $global = false): void
{
    Container::get()->fingerprintHelper->postProcessFingerprintForHash($id, $fingerprint, $global);
}

/**
 * Runs the callback, then saves the fingerprint for the id, unless it is the
 * saved one already. Returns whether the callback was run.
 *
 * @param bool $force  Runs the callback even when the fingerprint is the saved one
 * @param bool $global Shares the fingerprint between all the projects
 */
function fingerprint(callable $callback, string $id, string $fingerprint, bool $force = false, bool $global = false): bool
{
    if ($force || !fingerprint_exists($id, $fingerprint, $global)) {
        $callback();
        fingerprint_save($id, $fingerprint, $global);

        return true;
    }

    return false;
}

/**
 * Calls the callback every $intervalMs milliseconds, until it returns true, for
 * $timeout seconds at most. Returning null stops waiting at once.
 *
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
 * Waits until the port of the host accepts connections, for $timeout seconds at most.
 *
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
 * Waits until the URL can be opened, for $timeout seconds at most.
 *
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
 * Waits until the URL answers with the HTTP status, for $timeout seconds at most.
 *
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
 * Waits until the URL answers, and the response checker accepts the response,
 * for $timeout seconds at most.
 *
 * @param ?callable $responseChecker Receives the response, and returns true when it is the expected one, false to retry, or null to stop waiting
 *
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
 * Waits until the container checker, which receives the ID of the container,
 * returns true, for $timeout seconds at most.
 *
 * @throws TimeoutReachedException
 * @throws DockerContainerStateException When the container does not exist or is not running
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
 *
 * @param int-mask-of<Yaml::PARSE_*> $flags A bit field of PARSE_* constants to customize the YAML parser behavior
 */
function yaml_parse(string $content, int $flags = 0): mixed
{
    return Yaml::parse($content, $flags);
}

/**
 * @see Yaml::dump()
 *
 * @param int-mask-of<Yaml::DUMP_*> $flags A bit field of DUMP_* constants to customize the dumped YAML string
 */
function yaml_dump(mixed $input, int $inline = 2, int $indent = 4, int $flags = 0): string
{
    return Yaml::dump($input, $inline, $indent, $flags);
}

/**
 * Encrypts the content with the password: decrypt_with_password() decrypts it.
 */
function encrypt_with_password(string $content, string $password): string
{
    return Container::get()->symmetricCrypto->encrypt($content, $password);
}

/**
 * Decrypts a content encrypted by encrypt_with_password().
 */
function decrypt_with_password(string $content, string $password): string
{
    return Container::get()->symmetricCrypto->decrypt($content, $password);
}

/**
 * Encrypts the file to the destination, which defaults to the source path with a
 * ".enc" suffix.
 */
function encrypt_file_with_password(string $sourcePath, string $password, ?string $destinationPath = null): void
{
    if (!file_exists($sourcePath)) {
        throw new \InvalidArgumentException(\sprintf('The file "%s" does not exist.', $sourcePath));
    }

    $content = file_get_contents($sourcePath);
    if (false === $content) {
        throw new \RuntimeException(\sprintf('Failed to read the file "%s".', $sourcePath));
    }

    $encrypted = encrypt_with_password($content, $password);

    $destinationPath ??= "{$sourcePath}.enc";
    Container::get()->fs->dumpFile($destinationPath, $encrypted);

    $sourcePermissions = fileperms($sourcePath);
    if (false === $sourcePermissions) {
        throw new \RuntimeException(\sprintf('Failed to get the permissions of the file "%s".', $sourcePath));
    }

    fs()->chmod($destinationPath, $sourcePermissions);
}

/**
 * Decrypts a file encrypted by encrypt_file_with_password() to the destination,
 * which defaults to the source path without its ".enc" suffix, or with a ".dec"
 * suffix when this file exists or when there is no ".enc" suffix.
 */
function decrypt_file_with_password(string $sourcePath, string $password, ?string $destinationPath = null): void
{
    if (!file_exists($sourcePath)) {
        throw new \InvalidArgumentException(\sprintf('The file "%s" does not exist.', $sourcePath));
    }

    $content = file_get_contents($sourcePath);
    if (false === $content) {
        throw new \RuntimeException(\sprintf('Failed to read the file "%s".', $sourcePath));
    }

    $decrypted = decrypt_with_password($content, $password);

    if (null === $destinationPath) {
        if (str_ends_with($sourcePath, '.enc')) {
            $baseDestinationPath = substr($sourcePath, 0, -4);

            // If a file with the same name (without '.enc') already exists,
            // append '.dec' to avoid overwriting and prevent data loss
            $destinationPath = file_exists($baseDestinationPath)
                ? \sprintf('%s.dec', $baseDestinationPath)
                : $baseDestinationPath;
        } else {
            $destinationPath = \sprintf('%s.dec', $sourcePath);
        }
    }

    Container::get()->fs->dumpFile($destinationPath, $decrypted);

    $sourcePermissions = fileperms($sourcePath);
    if (false === $sourcePermissions) {
        throw new \RuntimeException(\sprintf('Failed to get the permissions of the file "%s".', $sourcePath));
    }

    fs()->chmod($destinationPath, $sourcePermissions);
}

/**
 * Stops when Castor is older than this version, like "v1.2.0" or "1.2.0".
 *
 * @throws MinimumVersionRequirementNotMetException
 */
function guard_min_version(string $minVersion): void
{
    $currentVersion = Container::get()->application->getVersion();

    $minVersion = u($minVersion)->ensureStart('v')->toString();
    if (version_compare($currentVersion, $minVersion, '<')) {
        throw new MinimumVersionRequirementNotMetException($minVersion, $currentVersion);
    }
}

/**
 * Opens the URLs or files with the default application of the system.
 */
function open(string ...$urls): void
{
    $command = match (true) {
        OsHelper::isMacOS() => 'open',
        OsHelper::isWindows() => 'start',
        default => 'xdg-open',
    };

    if (null === new ExecutableFinder()->find($command)) {
        throw new ExecutableNotFoundException($command);
    }

    $parallelCallbacks = [];

    foreach ($urls as $url) {
        $parallelCallbacks[] = static fn (): Process => run([$command, $url], context: context()->withQuiet(true));
    }

    parallel(...$parallelCallbacks);
}

/**
 * Compresses the file or directory into a zip archive, with the zip binary or
 * the zip PHP extension, which is preferred when there is a password as it keeps
 * it private.
 */
function zip(
    string $source,
    string $destination,
    #[\SensitiveParameter] ?string $password = null,
    CompressionMethod $compressionMethod = CompressionMethod::DEFLATE,
    int $compressionLevel = 6,
    bool $overwrite = false,
): void {
    Container::get()->zipArchiver->zip($source, $destination, $password, $compressionMethod, $compressionLevel, $overwrite);
}

/**
 * Like zip(), with the zip binary only: the password is visible to all the
 * users of the machine while it runs.
 */
function zip_binary(
    string $source,
    string $destination,
    #[\SensitiveParameter] ?string $password = null,
    CompressionMethod $compressionMethod = CompressionMethod::DEFLATE,
    int $compressionLevel = 6,
    bool $overwrite = false,
): void {
    Container::get()->zipArchiver->zipWithBinary($source, $destination, $password, $compressionMethod, $compressionLevel, $overwrite);
}

/**
 * Like zip(), with the zip PHP extension only.
 */
function zip_php(
    string $source,
    string $destination,
    #[\SensitiveParameter] ?string $password = null,
    CompressionMethod $compressionMethod = CompressionMethod::DEFLATE,
    int $compressionLevel = 6,
    bool $overwrite = false,
): void {
    Container::get()->zipArchiver->zipWithPhp($source, $destination, $password, $compressionMethod, $compressionLevel, $overwrite);
}

/**
 * Runs a PHP script or a phar with the PHP of Castor: it works with the static
 * binary too, when PHP is not installed.
 *
 * @param array<string|\Stringable>                      $arguments
 * @param (callable(string, string, Process) :void)|null $callback
 */
function run_php(string $pharPath, array $arguments = [], ?Context $context = null, ?callable $callback = null): Process
{
    return Container::get()
        ->phpRunner
        ->run($pharPath, $arguments, $context, $callback)
    ;
}

/**
 * Returns the value in ASCII kebab-case, like "hello-world" for "Hello World".
 */
function slug(string $value, string $separator = '-', ?string $locale = null): string
{
    return Container::get()
        ->slugger
        ->slug($value, $separator, $locale)
    ;
}

function terminal(): Terminal
{
    return Container::get()
        ->terminal
    ;
}
