<?php

namespace Castor;

use Castor\Console\Application;
use Castor\Console\Command\CompileCommand;
use Castor\Console\Command\ComposerCommand;
use Castor\Console\Command\DebugCommand;
use Castor\Console\Command\ExecuteCommand;
use Castor\Console\Command\InitCommand;
use Castor\Console\Command\RepackCommand;
use Castor\Console\Command\SelfUpdateCommand;
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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/** @internal */
final class Kernel extends AbstractKernel
{
    use KernelTrait {
        getKernelParameters as private getDefaultKernelParameters;
    }

    /**
     * @var list<Mount>
     */
    private array $mounts = [];

    private bool $chdirDeprecationTriggered = false;

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
        // The default implementation auto-detects the project dir from the kernel class
        // file location, which would resolve to Castor's own install dir (or the phar),
        // not the user's project.
        return $this->rootDir;
    }

    public function getCacheDir(): string
    {
        return (string) (getenv('CASTOR_CACHE_DIR') ?: PlatformHelper::getDefaultCacheDirectory());
    }

    public function getLogDir(): ?string
    {
        // The default implementation returns "{projectDir}/var/log" (or APP_LOG_DIR from
        // the environment), i.e. a directory inside the user's project, while Castor
        // never writes logs there. Null keeps "kernel.logs_dir" out of the parameters.
        return null;
    }

    public function getShareDir(): ?string
    {
        // Castor has no share-dir concept. The default implementation falls back to the
        // cache dir or the APP_SHARE_DIR env var, and since Castor often runs inside a
        // Symfony app, the user's APP_* env vars would leak into the kernel parameters.
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

        $container = $this->container;

        // KernelTrait aliases self::class to the synthetic "kernel" service
        $container->set('kernel', $this);
        $container->set(ContainerInterface::class, $container);
    }

    public function init(InputInterface $input, OutputInterface $output): void
    {
        $container = $this->getContainer();
        $container->set(InputInterface::class, $input);
        $container->set(OutputInterface::class, $output);

        $castorContainer = $container->get(Container::class);

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

            ->set(AsciiSlugger::class)

            ->set(DefaultNotifier::class)

            ->set(SymfonyStyle::class)

            ->set(Terminal::class)

            ->set(Container::class)
                ->public()

            ->set(ContainerInterface::class)
                ->synthetic()

            ->set(OutputInterface::class)
                ->synthetic()

            ->set(InputInterface::class)
                ->synthetic()

            ->set(ErrorHandler::class)
                ->synthetic()

            ->set(FileLinkFormatter::class)
        ;

        $app = $services->set(Application::class, $repacked ? \RepackedApplication::class : null)
                ->public()
                ->call('addCommand', [service(DebugCommand::class)])
                ->call('addCommand', [service(ExecuteCommand::class)])
                ->call('setDispatcher', [service(EventDispatcherInterface::class)])
                ->call('setCatchErrors', [true])
        ;
        if (!$repacked) {
            $app->call('addCommand', [service(SelfUpdateCommand::class)]);
        }
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

    /**
     * @return array<string, mixed>
     */
    protected function getKernelParameters(): array
    {
        return array_merge($this->getDefaultKernelParameters(), [
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
        // KernelTrait would dump the compiled container to disk and reuse it on the next
        // runs. Compile it fresh instead, to keep the same behavior as before.
        $container = $this->buildContainer();
        $container->compile(true);
        $this->container = $container;
    }

    protected function initializeBundles(): void
    {
        // KernelTrait discovers bundles from "{projectDir}/config/bundles.php", but the
        // project dir is the *user's* project (possibly a Symfony app): never read it.
        // ServicesBundle provides the core DI infrastructure (event dispatcher,
        // filesystem, listeners/subscribers autoconfiguration, ...).
        $bundle = new ServicesBundle();

        $this->bundles = [$bundle->getName() => $bundle];
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
            $descriptorsCollection->symfonyTaskDescriptors,
            $mount->path,
            $mount->path === $this->rootDir,
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

    /**
     * @param array<string> $contextNames
     */
    private function readDotContextFile(array $contextNames): ?string
    {
        $contextFile = $this->rootDir . '/.castor.context';
        if (!file_exists($contextFile)) {
            return null;
        }

        $content = trim((string) file_get_contents($contextFile));

        if (!$content || !\in_array($content, $contextNames, true)) {
            Container::get()->logger->error(\sprintf('The context "%s" defined in ".castor.context" file is not a valid context. Available contexts: %s.', $content ?: '(empty)', implode(', ', $contextNames)));

            return null;
        }

        return $content;
    }

    private function configureContext(InputInterface $input, OutputInterface $output, ContextRegistry $contextRegistry): void
    {
        // Only worth telling when there is a castor.php to put the define in, and never
        // while completing, where the output must stay clean. This method runs once per
        // mount, so it is told only once.
        if (!$this->chdirDeprecationTriggered && $this->hasCastorFile && '_complete' !== $input->getFirstArgument() && !\defined('CASTOR_USE_CHDIR')) {
            $this->chdirDeprecationTriggered = true;

            trigger_deprecation('castor/castor', '1.8.0', 'Not defining the "CASTOR_USE_CHDIR" constant is deprecated. Add "define(\'CASTOR_USE_CHDIR\', true);" at the top of your castor.php so Castor changes its current directory to the working directory of the context (the default in Castor 2.0), or define it to false to keep the current behavior.');
        }

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

        $currentContextName = $input->getParameterOption($contextOptions, onlyParams: true)
            ?: PlatformHelper::getEnv('CASTOR_CONTEXT')
            ?: $this->readDotContextFile($contextNames)
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
