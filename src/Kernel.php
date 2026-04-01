<?php

namespace Castor;

use Castor\Console\Application;
use Castor\Console\Command\CompileCommand;
use Castor\Console\Command\ComposerCommand;
use Castor\Console\Command\DebugCommand;
use Castor\Console\Command\ExecuteCommand;
use Castor\Console\Command\InitCommand;
use Castor\Console\Command\RepackCommand;
use Castor\Console\Output\VerbosityLevel;
use Castor\Event\AfterBootEvent;
use Castor\Event\BeforeBootEvent;
use Castor\Event\FunctionsResolvedEvent;
use Castor\Exception\CouldNotFindEntrypointException;
use Castor\Helper\PlatformHelper;
use Castor\Import\Importer;
use Castor\Import\Mount;
use Castor\Monolog\Processor\ProcessProcessor;
use Joli\JoliNotif\DefaultNotifier;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/** @internal */
final class Kernel extends AbstractKernel
{
    /**
     * @var list<Mount>
     */
    private array $mounts = [];

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $rootDir,
        private readonly bool $hasCastorFile,
        private readonly ?string $castorFilePath,
        private readonly bool $repacked,
    ) {
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->rootDir;
    }

    public function getCacheDir(): string
    {
        return (string) (getenv('CASTOR_CACHE_DIR') ?: PlatformHelper::getDefaultCacheDirectory());
    }

    public function getLogDir(): ?string
    {
        return null;
    }

    public function boot(): void
    {
        // AbstractKernel::boot() unconditionally sets SHELL_VERBOSITY=3 in debug mode,
        // which would make all console output verbose. Castor manages verbosity via
        // its own -v flag, so we restore the original value after boot.
        $shellVerbosity = getenv('SHELL_VERBOSITY');

        parent::boot();

        if (false === $shellVerbosity) {
            putenv('SHELL_VERBOSITY');
            unset($_ENV['SHELL_VERBOSITY'], $_SERVER['SHELL_VERBOSITY']);
        }

        if (!$this->container instanceof ContainerInterface) {
            throw new \LogicException('Container should be initialized after boot.');
        }

        $this->container->set(self::class, $this);
        $this->container->set(ContainerInterface::class, $this->container);
    }

    public function init(InputInterface $input, OutputInterface $output): void
    {
        $this->container->set(InputInterface::class, $input);
        $this->container->set(OutputInterface::class, $output);

        $castorContainer = $this->container->get(Container::class);

        if (!$castorContainer instanceof Container) {
            throw new \LogicException('Castor container should be initialized after boot.');
        }

        Container::set($castorContainer);

        $this->mount($input, $output);
    }

    public function addMount(Mount $mount): void
    {
        $this->mounts[] = $mount;
    }

    public function configureContainer(ContainerConfigurator $c): void
    {
        $services = $c->services();
        $repacked = $this->repacked;
        $hasCastorFile = $this->hasCastorFile;

        $services
            ->defaults()
                ->autowire()
                ->autoconfigure()
                ->bind('string $rootDir', '%root_dir%')
                ->bind('string $cacheDir', '%cache_dir%')
                ->bind('bool $hasCastorFile', '%has_castor_file%')
                ->bind('string $castorFilePath', '%castor_file_path%')

            ->load('Castor\\', __DIR__ . '/*')
                ->exclude([
                    __DIR__ . '/functions.php',
                    __DIR__ . '/functions-internal.php',
                    __DIR__ . '/Descriptor/*',
                    __DIR__ . '/Event/*',
                    __DIR__ . '/**/Exception/*',
                    __DIR__ . '/Kernel.php',
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

            ->set(SymfonyStyle::class)

            ->set(Terminal::class)

            ->set(Container::class)
                ->public()

            ->set(self::class)
                ->synthetic()
                ->public()

            ->set(ContainerInterface::class)
                ->synthetic()

            ->set(OutputInterface::class)
                ->synthetic()

            ->set(InputInterface::class)
                ->synthetic()

            ->set(ErrorHandler::class)
                ->synthetic()
        ;

        $app = $services->set(Application::class, $repacked ? \RepackedApplication::class : null)
                ->public()
                ->call('addCommand', [service(DebugCommand::class)])
                ->call('addCommand', [service(ExecuteCommand::class)])
                ->call('setDispatcher', [service(EventDispatcherInterface::class)])
                ->call('setCatchErrors', [true])
        ;
        if (!$repacked && $hasCastorFile) {
            $app
                ->call('addCommand', [service(ComposerCommand::class)])
                ->call('addCommand', [service(RepackCommand::class)])
                ->call('addCommand', [service(CompileCommand::class)])
            ;
        }

        if (!$hasCastorFile) {
            $app
                ->call('addCommand', [service(InitCommand::class)])
                ->call('setDefaultCommand', ['init'])
            ;
        }
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('kernel.event_subscriber')
        ;
        $container->addCompilerPass(new RegisterListenersPass());
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
    }

    protected function getKernelParameters(): array
    {
        return array_merge(parent::getKernelParameters(), [
            'container.runtime_mode' => 'cli=1',
            'root_dir' => $this->rootDir,
            '.default_cache_dir' => PlatformHelper::getDefaultCacheDirectory(),
            'event_dispatcher.event_aliases' => ConsoleEvents::ALIASES,
            'repacked' => $this->repacked,
            'cache_dir' => '%env(default:.default_cache_dir:CASTOR_CACHE_DIR)%',
            'composer_no_remote' => '%env(bool:default::CASTOR_NO_REMOTE)%',
            'context' => '',
            'env(CASTOR_GENERATE_STUBS)' => 'true',
            'generate_stubs' => '%env(bool:CASTOR_GENERATE_STUBS)%',
            'test' => '%env(bool:default::CASTOR_TEST)%',
            'use_output_section' => '%env(bool:default::CASTOR_USE_SECTION)%',
            'has_castor_file' => $this->hasCastorFile,
            'castor_file_path' => $this->castorFilePath,
        ]);
    }

    protected function initializeContainer(): void
    {
        $container = $this->buildContainer();
        $container->compile(true);
        $this->container = $container;
    }

    private function mount(InputInterface $input, OutputInterface $output): void
    {
        $c = Container::get();

        try {
            $c->eventDispatcher->dispatch(new BeforeBootEvent($c->application));

            $allowRemotePackage = $c->composer->isRemoteAllowed();

            $this->addMount(new Mount($this->rootDir, allowRemotePackage: $allowRemotePackage, allowEmptyEntrypoint: !$this->hasCastorFile, file: $this->castorFilePath));

            while ($mount = array_shift($this->mounts)) {
                $currentFunctions = get_defined_functions()['user'];
                $currentClasses = get_declared_classes();

                $this->load($mount, $currentFunctions, $currentClasses, $input, $output);
            }

            $c->eventDispatcher->dispatch(new AfterBootEvent($c->application));
        } catch (\Throwable $e) {
            $c->eventDispatcher->dispatch(new ConsoleErrorEvent($input, $output, $e), 'console.error');

            throw $e;
        }
    }

    /**
     * @param list<string>       $currentFunctions
     * @param list<class-string> $currentClasses
     */
    private function load(
        Mount $mount,
        array $currentFunctions,
        array $currentClasses,
        InputInterface $input,
        OutputInterface $output,
    ): void {
        $c = Container::get();

        if ($mount->allowRemotePackage) {
            $c->composer->install($mount->path);
        }

        if ($mount->path === $this->rootDir) {
            $c->composer->requireAutoload();
        }

        try {
            $this->requireEntrypoint($mount, $c->importer);
        } catch (CouldNotFindEntrypointException $e) {
            if (!$mount->allowEmptyEntrypoint) {
                throw $e;
            }
        }

        $descriptorsCollection = $c->functionResolver->resolveFunctions($currentFunctions, $currentClasses);

        // Apply mounts
        foreach ($descriptorsCollection->taskDescriptors as $taskDescriptor) {
            if ($mount->path !== $this->rootDir && !class_exists(\RepackedApplication::class)) {
                $taskDescriptor->workingDirectory = $mount->path;
            }
            if ($mount->namespacePrefix) {
                if ($taskDescriptor->taskAttribute->namespace) {
                    $taskDescriptor->taskAttribute->namespace = $mount->namespacePrefix . ':' . $taskDescriptor->taskAttribute->namespace;
                } else {
                    $taskDescriptor->taskAttribute->namespace = $mount->namespacePrefix;
                }
            }
        }

        $c->functionLoader->loadListeners($descriptorsCollection->listenerDescriptors);

        // Must load contexts before tasks, because tasks can be disabled
        // depending on the context. And it must be before executing
        // listeners too, to get the context there.
        $c->functionLoader->loadContexts($descriptorsCollection->contextDescriptors, $descriptorsCollection->contextGeneratorDescriptors);
        $this->configureContext($input, $output, $c->contextRegistry);

        $event = new FunctionsResolvedEvent(
            $descriptorsCollection->taskDescriptors,
            $descriptorsCollection->symfonyTaskDescriptors
        );
        $c->eventDispatcher->dispatch($event);

        $c->functionLoader->loadTasks(
            $event->taskDescriptors,
            $event->symfonyTaskDescriptors
        );
    }

    private function requireEntrypoint(Mount $mount, Importer $importer): void
    {
        $path = $mount->path;

        // It's an import, via a remote package, with a file specified
        if ($mount->file) {
            if (file_exists($file = $mount->path . '/' . $mount->file)) {
                $importer->importFile($file);

                return;
            }

            throw new CouldNotFindEntrypointException('Could not find "' . $mount->path . '/' . $mount->file . '" file.');
        }

        if (file_exists($file = $path . '/castor.php')) {
            $importer->importFile($file);
        } elseif (file_exists($file = $path . '/.castor/castor.php')) {
            $importer->importFile($file);
        } else {
            throw new CouldNotFindEntrypointException();
        }
    }

    private function configureContext(InputInterface $input, OutputInterface $output, ContextRegistry $contextRegistry): void
    {
        $contextRegistry->setDefaultIfEmpty();

        $contextNames = $contextRegistry->getNames();

        if (!$contextNames || 'list' === $input->getFirstArgument()) {
            $contextRegistry->setCurrentContext(new Context(
                verbosityLevel: VerbosityLevel::fromSymfonyOutput($output)
            ));

            return;
        }

        // autocomplete command already defined a -c option
        $isAutocomplete = '_complete' === $input->getFirstArgument();

        $contextOptions = ['--context'];
        if (!$isAutocomplete) {
            $contextOptions[] = '-c';
        }

        $currentContextName = $input->getParameterOption($contextOptions)
            ?: PlatformHelper::getEnv('CASTOR_CONTEXT')
            ?: $contextRegistry->getDefaultName();

        $application = Container::get()->application;
        $applicationDefinition = $application->getDefinition();
        $applicationDefinition->addOption(new InputOption(
            'context',
            $isAutocomplete ? null : 'c',
            InputOption::VALUE_REQUIRED,
            \sprintf('The context to use (%s)', implode('|', $contextNames)),
            $currentContextName,
            $contextNames,
        ));

        $context = $contextRegistry->get($currentContextName);

        if ($context->verbosityLevel->isNotConfigured()) {
            $context = $context->withVerbosityLevel(VerbosityLevel::fromSymfonyOutput($output));
        }

        $contextRegistry->setCurrentContext($context->withName($currentContextName));
    }
}
