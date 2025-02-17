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
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

trigger_deprecation('castor/castor', '0.16', 'The "%s" class is deprecated and will be removed in castor 1.0. Use the "%s::getContainer()" instead.', GlobalHelper::class, Application::class);

#[Exclude]
class GlobalHelper
{
    public static function setApplication(Application $application): void
    {
    }

    public static function getApplication(): Application
    {
        return Container::get()->application;
    }

    public static function getContextRegistry(): ContextRegistry
    {
        return Container::get()->contextRegistry;
    }

    public static function getEventDispatcher(): EventDispatcherInterface
    {
        return Container::get()->eventDispatcher;
    }

    public static function getFilesystem(): Filesystem
    {
        return Container::get()->fs;
    }

    public static function getHttpClient(): HttpClientInterface
    {
        return Container::get()->httpClient;
    }

    public static function getCache(): CacheItemPoolInterface&CacheInterface
    {
        return Container::get()->cache;
    }

    public static function getLogger(): LoggerInterface
    {
        return Container::get()->logger;
    }

    public static function getInput(): InputInterface
    {
        return Container::get()->input;
    }

    public static function getSectionOutput(): SectionOutput
    {
        return Container::get()->sectionOutput;
    }

    public static function getOutput(): OutputInterface
    {
        return Container::get()->output;
    }

    public static function getSymfonyStyle(): SymfonyStyle
    {
        return Container::get()->symfonyStyle;
    }

    /**
     * @return ($allowNull is true ? ?Command : Command)
     */
    public static function getCommand(bool $allowNull = false): ?Command
    {
        return Container::get()->getCommand($allowNull);
    }

    public static function getContext(?string $name = null): Context
    {
        return Container::get()->getContext($name);
    }

    public static function getVariable(string $key, mixed $default = null): mixed
    {
        return Container::get()->getVariable($key, $default);
    }
}
