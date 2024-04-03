<?php

namespace Castor\Console;

use Castor\Console\Command\SymfonyTaskCommand;
use Castor\Console\Output\VerbosityLevel;
use Castor\Container;
use Castor\Context;
use Castor\ContextRegistry;
use Castor\Descriptor\ContextDescriptor;
use Castor\Descriptor\ContextGeneratorDescriptor;
use Castor\Descriptor\ListenerDescriptor;
use Castor\Descriptor\SymfonyTaskDescriptor;
use Castor\Descriptor\TaskDescriptor;
use Castor\Descriptor\TaskDescriptorCollection;
use Castor\Event\AfterApplicationInitializationEvent;
use Castor\Event\BeforeApplicationInitializationEvent;
use Castor\Factory\TaskCommandFactory;
use Castor\FunctionFinder;
use Castor\Helper\PlatformHelper;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** @internal */
class Application extends SymfonyApplication
{
    public const NAME = 'castor';
    public const VERSION = 'v0.15.0';

    private static Container $container;

    private Command $command;

    public function __construct(
        private readonly string $rootDir,
        private readonly ContainerBuilder $containerBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FunctionFinder $functionFinder,
        private readonly ContextRegistry $contextRegistry,
        private readonly TaskCommandFactory $taskCommandFactory,
    ) {
        parent::__construct(static::NAME, static::VERSION);
    }

    public static function getContainer(): Container
    {
        return self::$container ?? throw new \LogicException('Container not available yet.');
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

        $event = new BeforeApplicationInitializationEvent($this);
        $this->eventDispatcher->dispatch($event);

        // @phpstan-ignore-next-line
        self::$container = $this->containerBuilder->get(Container::class);

        $descriptors = $this->initializeApplication($input);

        // Must be done after the initializeApplication() call, to ensure all
        // contexts have been created; but before the adding of task, because we
        // may want to seek in the context to know if the command is enabled
        $this->configureContext($input, $output);

        $event = new AfterApplicationInitializationEvent($this, $descriptors);
        $this->eventDispatcher->dispatch($event);
        $descriptors = $event->taskDescriptorCollection;

        foreach ($descriptors->taskDescriptors as $taskDescriptor) {
            $this->add($this->taskCommandFactory->createTask($taskDescriptor));
        }
        foreach ($descriptors->symfonyTaskDescriptors as $symfonyTaskDescriptor) {
            $this->add(SymfonyTaskCommand::createFromDescriptor($symfonyTaskDescriptor));
        }

        return parent::doRun($input, $output);
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $this->command = $command;

        return parent::doRunCommand($command, $input, $output);
    }

    private function initializeApplication(InputInterface $input): TaskDescriptorCollection
    {
        $functionsRootDir = $this->rootDir;
        if (class_exists(\RepackedApplication::class)) {
            $functionsRootDir = \RepackedApplication::ROOT_DIR;
        }

        $descriptors = $this->functionFinder->findFunctions($functionsRootDir);
        $tasks = [];
        $symfonyTasks = [];
        foreach ($descriptors as $descriptor) {
            if ($descriptor instanceof TaskDescriptor) {
                $tasks[] = $descriptor;
            } elseif ($descriptor instanceof SymfonyTaskDescriptor) {
                $symfonyTasks[] = $descriptor;
            } elseif ($descriptor instanceof ContextDescriptor) {
                $this->contextRegistry->addDescriptor($descriptor);
            } elseif ($descriptor instanceof ContextGeneratorDescriptor) {
                foreach ($descriptor->generators as $name => $generator) {
                    $this->contextRegistry->addContext($name, $generator);
                }
            } elseif ($descriptor instanceof ListenerDescriptor && null !== $descriptor->reflectionFunction->getClosure()) {
                $this->eventDispatcher->addListener(
                    $descriptor->asListener->event,
                    $descriptor->reflectionFunction->getClosure(),
                    $descriptor->asListener->priority
                );
            }
        }

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

        return new TaskDescriptorCollection($tasks, $symfonyTasks);
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
