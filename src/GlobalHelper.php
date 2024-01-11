<?php

namespace Castor;

use Castor\Console\Application;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GlobalHelper
{
    private static Application $application;

    public static function setApplication(Application $application): void
    {
        self::$application = $application;
    }

    public static function getApplication(): Application
    {
        return self::$application ?? throw new \LogicException('Application not available yet.');
    }

    public static function getContextRegistry(): ContextRegistry
    {
        return self::getApplication()->contextRegistry;
    }

    public static function getEventDispatcher(): EventDispatcher
    {
        return self::getApplication()->eventDispatcher;
    }

    public static function getFilesystem(): Filesystem
    {
        return self::getApplication()->fs;
    }

    public static function getHttpClient(): HttpClientInterface
    {
        return self::getApplication()->httpClient;
    }

    public static function getCache(): CacheItemPoolInterface&CacheInterface
    {
        return self::getApplication()->cache;
    }

    public static function getLogger(): Logger
    {
        return self::getApplication()->logger;
    }

    public static function getInput(): InputInterface
    {
        return self::getApplication()->getInput();
    }

    public static function getSectionOutput(): SectionOutput
    {
        return self::getApplication()->getSectionOutput();
    }

    public static function getOutput(): OutputInterface
    {
        return self::getApplication()->getOutput();
    }

    public static function getSymfonyStyle(): SymfonyStyle
    {
        return self::getApplication()->getSymfonyStyle();
    }

    public static function getCommand(): Command
    {
        return self::getApplication()->getCommand();
    }

    public static function getContext(string $name = null): Context
    {
        return self::getContextRegistry()->get($name);
    }

    public static function getVariable(string $key, mixed $default = null): mixed
    {
        return self::getContextRegistry()->getVariable($key, $default);
    }
}
