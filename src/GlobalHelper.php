<?php

namespace Castor;

use Castor\Console\Application;
use Castor\GlobalHelper as CastorGlobalHelper;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GlobalHelper
{
    private static Application $application;
    private static InputInterface $input;
    private static SectionOutput $sectionOutput;
    private static SymfonyStyle $symfonyStyle;
    private static Logger $logger;
    private static ContextRegistry $contextRegistry;
    private static Command $command;
    private static Context $initialContext;
    private static Filesystem $fs;
    private static HttpClientInterface $httpClient;
    private static CacheItemPoolInterface&CacheInterface $cache;
    private static ExpressionLanguage $expressionLanguage;

    public static function setApplication(Application $application): void
    {
        self::$application = $application;
    }

    public static function getApplication(): Application
    {
        return self::$application ?? throw new \LogicException('Application not available yet.');
    }

    public static function setInput(InputInterface $input): void
    {
        self::$input = $input;
    }

    public static function getInput(): InputInterface
    {
        return self::$input ?? throw new \LogicException('Input not available yet.');
    }

    public static function getOutput(): OutputInterface
    {
        return self::getSectionOutput()->getConsoleOutput();
    }

    public static function setSectionOutput(SectionOutput $output): void
    {
        self::$sectionOutput = $output;
    }

    public static function getSectionOutput(): SectionOutput
    {
        return self::$sectionOutput ?? throw new \LogicException('Section output not available yet.');
    }

    public static function getSymfonyStyle(): SymfonyStyle
    {
        return self::$symfonyStyle ??= new SymfonyStyle(self::getInput(), self::getOutput());
    }

    public static function setLogger(Logger $logger): void
    {
        self::$logger = $logger;
    }

    public static function getLogger(): Logger
    {
        return self::$logger ?? throw new \LogicException('Logger not available yet.');
    }

    public static function setContextRegistry(ContextRegistry $contextRegistry): void
    {
        self::$contextRegistry = $contextRegistry;
    }

    public static function getContextRegistry(): ContextRegistry
    {
        return self::$contextRegistry ?? throw new \LogicException('ContextRegistry not available yet.');
    }

    public static function setCommand(Command $command): void
    {
        self::$command = $command;
    }

    public static function setInitialContext(Context $initialContext): void
    {
        self::$initialContext = $initialContext;
    }

    public static function getInitialContext(): Context
    {
        // We always need a default context, for example when using run() in a context builder
        return self::$initialContext ?? new Context();
    }

    public static function getContext(string $name = null): Context
    {
        if (null === $name) {
            return self::$initialContext ?? new Context();
        }

        return self::getContextRegistry()->get($name);
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
    public static function getVariable(string $key, mixed $default = null): mixed
    {
        $initialContext = self::getInitialContext();

        if (!isset($initialContext[$key])) {
            return $default;
        }

        return $initialContext[$key];
    }

    public static function getCommand(): Command
    {
        return self::$command ?? throw new \LogicException('Command not available yet.');
    }

    public static function getFilesystem(): Filesystem
    {
        return self::$fs ??= new Filesystem();
    }

    public static function setHttpClient(HttpClientInterface $httpClient): void
    {
        self::$httpClient = $httpClient;
    }

    public static function getHttpClient(): HttpClientInterface
    {
        return self::$httpClient ??= HttpClient::create([
            'headers' => [
                'User-Agent' => 'Castor/' . Application::VERSION,
            ],
        ]);
    }

    public static function setCache(CacheItemPoolInterface&CacheInterface $cache): void
    {
        self::$cache = $cache;
    }

    public static function getCache(): CacheItemPoolInterface&CacheInterface
    {
        return self::$cache ?? throw new \LogicException('Cache not available yet.');
    }

    public static function setupDefaultCache(): void
    {
        if (!isset(self::$cache)) {
            self::setCache(new FilesystemAdapter(directory: sys_get_temp_dir() . '/castor'));
        }
    }

    public static function getExpressionLanguage(): ExpressionLanguage
    {
        if (isset(self::$expressionLanguage)) {
            return self::$expressionLanguage;
        }

        self::$expressionLanguage = new ExpressionLanguage();
        self::$expressionLanguage->addFunction(new ExpressionFunction(
            'var',
            fn () => throw new \LogicException('This function can only be used in expressions.'),
            fn ($vars, ...$args) => CastorGlobalHelper::getVariable(...$args),
        ));

        return self::$expressionLanguage;
    }
}
