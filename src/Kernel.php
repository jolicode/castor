<?php

namespace Castor;

use Castor\Console\Application;
use Castor\Console\Output\VerbosityLevel;
use Castor\Descriptor\DescriptorsCollection;
use Castor\Descriptor\TaskDescriptorCollection;
use Castor\Event\AfterApplicationInitializationEvent;
use Castor\Event\AfterBootEvent;
use Castor\Event\BeforeBootEvent;
use Castor\Event\FunctionsResolvedEvent;
use Castor\Exception\CouldNotFindEntrypointException;
use Castor\Function\FunctionLoader;
use Castor\Function\FunctionResolver;
use Castor\Helper\PlatformHelper;
use Castor\Import\Importer;
use Castor\Import\Mount;
use Castor\Import\Remote\Composer;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;

/** @internal */
final class Kernel
{
    /**
     * @var list<Mount>
     */
    private array $mounts = [];

    public function __construct(
        #[Autowire(lazy: true)]
        private readonly Application $application,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $rootDir,
        #[Autowire(lazy: true)]
        private readonly Importer $importer,
        #[Autowire(lazy: true)]
        private readonly Composer $composer,
        private readonly FunctionResolver $functionResolver,
        private readonly FunctionLoader $functionLoader,
        private readonly ContextRegistry $contextRegistry,
    ) {
    }

    public function boot(InputInterface $input, OutputInterface $output): void
    {
        try {
            $this->eventDispatcher->dispatch(new BeforeBootEvent($this->application));

            $allowRemotePackage = $this->composer->isRemoteAllowed();

            $this->addMount(new Mount($this->rootDir, allowRemotePackage: $allowRemotePackage));

            while ($mount = array_shift($this->mounts)) {
                $currentFunctions = get_defined_functions()['user'];
                $currentClasses = get_declared_classes();

                $this->load($mount, $currentFunctions, $currentClasses, $input, $output);
            }

            $this->eventDispatcher->dispatch(new AfterBootEvent($this->application));
        } catch (\Throwable $e) {
            $this->eventDispatcher->dispatch(new ConsoleErrorEvent($input, $output, $e), 'console.error');

            throw $e;
        }
    }

    public function addMount(Mount $mount): void
    {
        $this->mounts[] = $mount;
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
        if ($mount->allowRemotePackage) {
            $this->composer->install($mount->path);
        }

        if ($mount->path === $this->rootDir) {
            $this->composer->requireAutoload();
        }

        try {
            $this->requireEntrypoint($mount);
        } catch (CouldNotFindEntrypointException $e) {
            if (!$mount->allowEmptyEntrypoint) {
                throw $e;
            }
        }

        $descriptorsCollection = $this->functionResolver->resolveFunctions($currentFunctions, $currentClasses);

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

        $this->functionLoader->loadListeners($descriptorsCollection->listenerDescriptors);

        // Must load contexts before tasks, because tasks can be disabled
        // depending on the context. And it must be before executing
        // listeners too, to get the context there.
        $this->functionLoader->loadContexts($descriptorsCollection->contextDescriptors, $descriptorsCollection->contextGeneratorDescriptors);
        $this->configureContext($input, $output);

        if ($this->eventDispatcher->hasListeners(AfterApplicationInitializationEvent::class)) {
            trigger_deprecation('castor', '0.16', 'The "%s" class is deprecated, use "%s" instead.', AfterApplicationInitializationEvent::class, FunctionsResolvedEvent::class);
            $event = new AfterApplicationInitializationEvent(
                $this->application,
                new TaskDescriptorCollection(
                    $descriptorsCollection->taskDescriptors,
                    $descriptorsCollection->symfonyTaskDescriptors
                ),
            );
            $this->eventDispatcher->dispatch($event);
            $taskDescriptorCollection = $event->taskDescriptorCollection;

            $descriptorsCollection = new DescriptorsCollection(
                $descriptorsCollection->contextDescriptors,
                $descriptorsCollection->contextGeneratorDescriptors,
                $descriptorsCollection->listenerDescriptors,
                $taskDescriptorCollection->taskDescriptors,
                $taskDescriptorCollection->symfonyTaskDescriptors,
            );
        }

        $event = new FunctionsResolvedEvent(
            $descriptorsCollection->taskDescriptors,
            $descriptorsCollection->symfonyTaskDescriptors
        );
        $this->eventDispatcher->dispatch($event);

        $this->functionLoader->loadTasks(
            $event->taskDescriptors,
            $event->symfonyTaskDescriptors
        );
    }

    private function requireEntrypoint(Mount $mount): void
    {
        $path = $mount->path;

        // It's an import, via a remote package, with a file specified
        if ($mount->file) {
            $this->importer->importFile($mount->path . '/' . $mount->file);

            return;
        }

        if (file_exists($file = $path . '/castor.php')) {
            $this->importer->importFile($file);
        } elseif (file_exists($file = $path . '/.castor/castor.php')) {
            $this->importer->importFile($file);
        } else {
            throw new CouldNotFindEntrypointException();
        }

        $castorDirectory = $path . '/castor';
        if (is_dir($castorDirectory)) {
            trigger_deprecation('castor', '0.15', 'Autoloading functions from the "/castor/" directory is deprecated. Import files by yourself with the "castor\import()" function.');
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->in($castorDirectory)
            ;

            foreach ($files as $file) {
                $this->importer->importFile($file->getPathname());
            }
        }
    }

    private function configureContext(InputInterface $input, OutputInterface $output): void
    {
        $this->contextRegistry->setDefaultIfEmpty();

        $contextNames = $this->contextRegistry->getNames();

        if (!$contextNames || ('_complete' === $input->getFirstArgument() || 'list' === $input->getFirstArgument())) {
            $this->contextRegistry->setCurrentContext(new Context(
                verbosityLevel: VerbosityLevel::fromSymfonyOutput($output)
            ));

            return;
        }

        $currentContextName = PlatformHelper::getEnv('CASTOR_CONTEXT')
            ?: $input->getParameterOption(['--context', '-c'])
            ?: $this->contextRegistry->getDefaultName();

        $applicationDefinition = $this->application->getDefinition();
        $applicationDefinition->addOption(new InputOption(
            'context',
            'c',
            InputOption::VALUE_REQUIRED,
            \sprintf('The context to use (%s)', implode('|', $contextNames)),
            $currentContextName,
            $contextNames,
        ));

        $context = $this
            ->contextRegistry
            ->get($currentContextName)
        ;

        if ($context->verbosityLevel->isNotConfigured()) {
            $context = $context->withVerbosityLevel(VerbosityLevel::fromSymfonyOutput($output));
        }

        $this->contextRegistry->setCurrentContext($context->withName($currentContextName));
    }
}
