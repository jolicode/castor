<?php

namespace Castor\Console;

use Castor\Console\Command\CompileCommand;
use Castor\Console\Command\ComposerCommand;
use Castor\Console\Command\DebugCommand;
use Castor\Console\Command\ExecuteCommand;
use Castor\Console\Command\InitCommand;
use Castor\Console\Command\RepackCommand;
use Castor\Container;
use Castor\Helper\PathHelper;
use Castor\Helper\PlatformHelper;
use Castor\Monolog\Processor\ProcessProcessor;
use Castor\👾\FixLazyServicePass;
use Joli\JoliNotif\DefaultNotifier;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\VarDumper\Caster\StubCaster;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/** @internal */
#[Exclude]
class ApplicationFactory
{
    public static function create(): SymfonyApplication
    {
        $errorHandler = self::configureDebug();

        if (class_exists(\RepackedApplication::class)) {
            $rootDir = \RepackedApplication::ROOT_DIR;
            $repacked = true;
        } else {
            $repacked = false;
        }

        $hasCastorFile = true;

        if (!$repacked) {
            try {
                $rootDir = PathHelper::getRoot();
            } catch (\RuntimeException $e) {
                $rootDir = getcwd();
                $hasCastorFile = false;
            }
        }

        $container = self::buildContainer($repacked, $hasCastorFile);
        $container->getParameterBag()->add([
            'root_dir' => $rootDir,
            '.default_cache_dir' => PlatformHelper::getDefaultCacheDirectory(),
            'event_dispatcher.event_aliases' => ConsoleEvents::ALIASES,
            'repacked' => $repacked,
            'cache_dir' => '%env(default:.default_cache_dir:CASTOR_CACHE_DIR)%',
            'composer_no_remote' => '%env(bool:default::CASTOR_NO_REMOTE)%',
            'context' => '%env(default::CASTOR_CONTEXT)%',
            'env(CASTOR_GENERATE_STUBS)' => 'true',
            'generate_stubs' => '%env(bool:CASTOR_GENERATE_STUBS)%',
            'test' => '%env(bool:default::CASTOR_TEST)%',
            'use_output_section' => '%env(bool:default::CASTOR_USE_SECTION)%',
            'has_castor_file' => $hasCastorFile,
        ]);

        $container->addCompilerPass(new FixLazyServicePass(), PassConfig::TYPE_OPTIMIZE);
        $container->compile(true);

        $container->set(ContainerInterface::class, $container);
        $container->set(ErrorHandler::class, $errorHandler);

        // @phpstan-ignore-next-line
        return $container->get(Application::class);
    }

    private static function configureDebug(): ErrorHandler
    {
        $errorHandler = ErrorHandler::register();

        AbstractCloner::$defaultCasters[Application::class] = StubCaster::cutInternals(...);
        AbstractCloner::$defaultCasters[Event::class] = StubCaster::cutInternals(...);

        return $errorHandler;
    }

    private static function buildContainer(bool $repacked, bool $hasCastorFile): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('kernel.event_subscriber')
        ;
        $container->addCompilerPass(new RegisterListenersPass());
        // from https://github.com/symfony/symfony/blob/6.4/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/FrameworkExtension.php#L676-L685
        $container->registerAttributeForAutoconfiguration(AsEventListener::class, static function (ChildDefinition $definition, AsEventListener $attribute, \Reflector $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new \LogicException(\sprintf('AsEventListener attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('kernel.event_listener', $tagAttributes);
        });

        $phpLoader = new PhpFileLoader($container, new FileLocator());
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $phpLoader, $instanceof, __DIR__, __FILE__);
        self::configureContainer($configurator, $repacked, $hasCastorFile);

        return $container;
    }

    private static function configureContainer(ContainerConfigurator $c, bool $repacked, bool $hasCastorFile): void
    {
        $services = $c->services();

        $services
            ->defaults()
                ->autowire()
                ->autoconfigure()
                ->bind('string $rootDir', '%root_dir%')
                ->bind('string $cacheDir', '%cache_dir%')
                ->bind('bool $hasCastorFile', '%has_castor_file%')

            ->load('Castor\\', __DIR__ . '/../*')
                ->exclude([
                    __DIR__ . '/../functions.php',
                    __DIR__ . '/../functions-internal.php',
                    __DIR__ . '/../Descriptor/*',
                    __DIR__ . '/../Event/*',
                    __DIR__ . '/../**/Exception/*',
                ])

            ->set(CacheInterface::class, FilesystemAdapter::class)
                ->args([
                    '$directory' => '%cache_dir%',
                ])
            ->alias(CacheItemPoolInterface::class . '&' . CacheInterface::class, CacheInterface::class)

            ->set(HttpClientInterface::class)
                ->factory([HttpClient::class, 'create'])
                ->args([
                    '$defaultOptions' => [
                        'headers' => [
                            'User-Agent' => 'Castor/' . Application::VERSION,
                        ],
                    ],
                ])

            ->set(Logger::class)
                ->args([
                    '$name' => 'castor',
                    '$processors' => [
                        service(ProcessProcessor::class),
                    ],
                ])
            ->alias(LoggerInterface::class, Logger::class)

            ->set(EventDispatcher::class)
            ->alias(EventDispatcherInterface::class, EventDispatcher::class)
            ->alias('event_dispatcher', EventDispatcherInterface::class)

            ->set(Filesystem::class)

            ->set(AsciiSlugger::class)

            ->set(DefaultNotifier::class)

            ->set(Container::class)
                ->public()

            ->set(ContainerInterface::class)
                ->synthetic()

            ->set(OutputInterface::class)
                ->synthetic()

            ->set(InputInterface::class)
                ->synthetic()

            ->set(SymfonyStyle::class)

            ->set(ErrorHandler::class)
                ->synthetic()
        ;

        $app = $services->set(Application::class, $repacked ? \RepackedApplication::class : null)
                ->public()
                ->args([
                    '$containerBuilder' => service(ContainerInterface::class),
                ])
                ->call('add', [service(DebugCommand::class)])
                ->call('add', [service(ExecuteCommand::class)])
                ->call('setDispatcher', [service(EventDispatcherInterface::class)])
                ->call('setCatchErrors', [true])
        ;
        if (!$repacked && $hasCastorFile) {
            $app
                ->call('add', [service(ComposerCommand::class)])
                ->call('add', [service(RepackCommand::class)])
                ->call('add', [service(CompileCommand::class)])
            ;
        }

        if (!$hasCastorFile) {
            $app
                ->call('add', [service(InitCommand::class)])
                ->call('setDefaultCommand', ['init'])
            ;
        }
    }
}
