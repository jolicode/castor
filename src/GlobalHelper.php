<?php

namespace Castor;

use Castor\Console\Application;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;

class GlobalHelper
{
    private static Application $application;
    private static InputInterface $input;
    private static OutputInterface $output;
    private static SymfonyStyle $symfonyStyle;
    private static Logger $logger;
    private static Command $command;
    private static Context $initialContext;
    private static Filesystem $fs;
    private static CacheItemPoolInterface&CacheInterface $cache;

    public static function setApplication(Application $application): void
    {
        self::$application = $application;
    }

    public static function getApplication(): Application
    {
        return self::$application ?? throw new \LogicException('Application not set yet.');
    }

    public static function setInput(InputInterface $input): void
    {
        self::$input = $input;
    }

    public static function getInput(): InputInterface
    {
        return self::$input ?? throw new \LogicException('Input not set yet.');
    }

    public static function setOutput(OutputInterface $output): void
    {
        self::$output = $output;
    }

    public static function getOutput(): OutputInterface
    {
        return self::$output ?? throw new \LogicException('Output not set yet.');
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
        return self::$logger ?? throw new \LogicException('Logger not set yet.');
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

    public static function getVariable(string $key, mixed $defaultValue = null): mixed
    {
        $initialContext = self::getInitialContext();

        if (!isset($initialContext[$key])) {
            return $defaultValue;
        }

        return $initialContext[$key];
    }

    public static function getCommand(): Command
    {
        return self::$command ?? throw new \LogicException('Command not set yet.');
    }

    public static function getFilesystem(): Filesystem
    {
        return self::$fs ??= new Filesystem();
    }

    public static function setCache(CacheItemPoolInterface&CacheInterface $cache): void
    {
        self::$cache = $cache;
    }

    public static function getCache(): CacheItemPoolInterface&CacheInterface
    {
        return self::$cache ?? throw new \LogicException('Cache not set yet.');
    }

    public static function setupCacheIfNeeded(): void
    {
        if (!isset(self::$cache)) {
            self::setCache(new FilesystemAdapter(directory: sys_get_temp_dir() . '/castor'));
        }
    }
}
