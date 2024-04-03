<?php

namespace Castor;

use Castor\Console\Application;
use Castor\Console\Output\SectionOutput;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

trigger_deprecation('castor/castor', '0.16', 'The "%s" class is deprecated and will be removed in castor 1.0. Use the "%s::getContainer()" instead.', __CLASS__, Application::class);

class GlobalHelper
{
    public static function setApplication(Application $application): void
    {
    }

    public static function getApplication(): Application
    {
        return Application::getContainer()->application;
    }

    public static function getContextRegistry(): ContextRegistry
    {
        return Application::getContainer()->contextRegistry;
    }

    public static function getEventDispatcher(): EventDispatcherInterface
    {
        return Application::getContainer()->eventDispatcher;
    }

    public static function getFilesystem(): Filesystem
    {
        return Application::getContainer()->fs;
    }

    public static function getHttpClient(): HttpClientInterface
    {
        return Application::getContainer()->httpClient;
    }

    public static function getCache(): CacheItemPoolInterface&CacheInterface
    {
        return Application::getContainer()->cache;
    }

    public static function getLogger(): LoggerInterface
    {
        return Application::getContainer()->logger;
    }

    public static function getInput(): InputInterface
    {
        return Application::getContainer()->input;
    }

    public static function getSectionOutput(): SectionOutput
    {
        return Application::getContainer()->sectionOutput;
    }

    public static function getOutput(): OutputInterface
    {
        return Application::getContainer()->output;
    }

    public static function getSymfonyStyle(): SymfonyStyle
    {
        return Application::getContainer()->symfonyStyle;
    }

    /**
     * @return ($allowNull is true ? ?Command : Command)
     */
    public static function getCommand(bool $allowNull = false): ?Command
    {
        return Application::getContainer()->getCommand($allowNull);
    }

    public static function getContext(?string $name = null): Context
    {
        return Application::getContainer()->getContext($name);
    }

    public static function getVariable(string $key, mixed $default = null): mixed
    {
        return Application::getContainer()->getVariable($key, $default);
    }
}
