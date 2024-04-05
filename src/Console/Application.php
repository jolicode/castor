<?php

namespace Castor\Console;

use Castor\Console\Command\SymfonyTaskCommand;
use Castor\Console\Output\VerbosityLevel;
use Castor\Container;
use Castor\Context;
use Castor\ContextRegistry;
use Castor\Event\AfterApplicationInitializationEvent;
use Castor\Event\BeforeApplicationBootEvent;
use Castor\Event\BeforeApplicationInitializationEvent;
use Castor\Factory\TaskCommandFactory;
use Castor\Function\FunctionLoader;
use Castor\Helper\PlatformHelper;
use Castor\Import\Importer;
use Castor\Import\Kernel;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** @internal */
class Application extends SymfonyApplication
{
    public const NAME = 'castor';
    public const VERSION = 'v0.15.0';

    private Command $command;

    public function __construct(
        private readonly string $rootDir,
        private readonly ContainerBuilder $containerBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(lazy: true)]
        private readonly Importer $importer,
        private readonly FunctionLoader $functionLoader,
        private readonly Kernel $kernel,
        private readonly ContextRegistry $contextRegistry,
        private readonly TaskCommandFactory $taskCommandFactory,
    ) {
        parent::__construct(static::NAME, static::VERSION);
    }

    /**
     * @return ($allowNull is true ? ?Command : Command)
     */
    public function getCommand(bool $allowNull = false): ?Command
    {
        return $this->command ?? ($allowNull ? null : throw new \LogicException('Command not available yet.'));
    }

    // We do all the logic as late as possible to ensure the exception handler
    // is registered
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->containerBuilder->set(InputInterface::class, $input);
        $this->containerBuilder->set(OutputInterface::class, $output);

        // @phpstan-ignore-next-line
        Container::set($this->containerBuilder->get(Container::class));

        $this->eventDispatcher->dispatch(new BeforeApplicationBootEvent($this));

        $currentFunctions = get_defined_functions()['user'];
        $currentClasses = get_declared_classes();

        $functionsRootDir = $this->rootDir;
        if (class_exists(\RepackedApplication::class)) {
            $functionsRootDir = \RepackedApplication::ROOT_DIR;
        }
        $this->importer->require($functionsRootDir);

        $this->eventDispatcher->dispatch(new BeforeApplicationInitializationEvent($this));

        $taskDescriptorCollection = $this->functionLoader->load($currentFunctions, $currentClasses);
        $taskDescriptorCollection = $taskDescriptorCollection->merge($this->kernel->mount());

        $this->initializeApplication($input);

        // Must be done after the initializeApplication() call, to ensure all
        // contexts have been created; but before the adding of task, because we
        // may want to seek in the context to know if the command is enabled
        $this->configureContext($input, $output);

        $event = new AfterApplicationInitializationEvent($this, $taskDescriptorCollection);
        $this->eventDispatcher->dispatch($event);
        $taskDescriptorCollection = $event->taskDescriptorCollection;

        foreach ($taskDescriptorCollection->taskDescriptors as $taskDescriptor) {
            $this->add($this->taskCommandFactory->createTask($taskDescriptor));
        }
        foreach ($taskDescriptorCollection->symfonyTaskDescriptors as $symfonyTaskDescriptor) {
            $this->add(SymfonyTaskCommand::createFromDescriptor($symfonyTaskDescriptor));
        }

        return parent::doRun($input, $output);
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $this->command = $command;

        return parent::doRunCommand($command, $input, $output);
    }

    private function initializeApplication(InputInterface $input): void
    {
        $this->contextRegistry->setDefaultIfEmpty();

        $contextNames = $this->contextRegistry->getNames();

        if ($contextNames) {
            $defaultContext = PlatformHelper::getEnv('CASTOR_CONTEXT') ?: $this->contextRegistry->getDefaultName();

            $this->getDefinition()->addOption(new InputOption(
                'context',
                '_complete' === $input->getFirstArgument() || 'list' === $input->getFirstArgument() ? null : 'c',
                InputOption::VALUE_REQUIRED,
                sprintf('The context to use (%s)', implode('|', $contextNames)),
                $defaultContext,
                $contextNames,
            ));
        }

        $this->getDefinition()->addOption(
            new InputOption(
                'no-remote',
                null,
                InputOption::VALUE_NONE,
                'Skip the import of all remote remote packages',
            )
        );

        $this->getDefinition()->addOption(
            new InputOption(
                'update-remotes',
                null,
                InputOption::VALUE_NONE,
                'Force the update of remote packages',
            )
        );
    }

    private function configureContext(InputInterface $input, OutputInterface $output): void
    {
        try {
            $input->bind($this->getDefinition());
        } catch (ExceptionInterface) {
            // not an issue if parsing gone wrong, we'll just use the default
            // context and it will fail later anyway
        }

        // occurs when running `castor -h`, or if no context is defined
        if (!$input->hasOption('context')) {
            $this->contextRegistry->setCurrentContext(new Context());

            return;
        }

        $context = $this
            ->contextRegistry
            ->get($input->getOption('context'))
        ;

        if ($context->verbosityLevel->isNotConfigured()) {
            $context = $context->withVerbosityLevel(VerbosityLevel::fromSymfonyOutput($output));
        }

        $this->contextRegistry->setCurrentContext($context->withName($input->getOption('context')));
    }
}
