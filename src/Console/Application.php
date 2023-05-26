<?php

namespace Castor\Console;

use Castor\Console\Command\TaskCommand;
use Castor\Context;
use Castor\ContextBuilder;
use Castor\ContextRegistry;
use Castor\FunctionFinder;
use Castor\Stub\StubsGenerator;
use Castor\TaskDescriptor;
use Castor\VerbosityLevel;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** @internal */
class Application extends SymfonyApplication
{
    public const VERSION = 'v0.1.0';

    public readonly ContextRegistry $contextRegistry;

    public function __construct(
        private readonly string $rootDir,
    ) {
        parent::__construct('castor', self::VERSION);

        $this->contextRegistry = new ContextRegistry();
    }

    // We do all the logic as late as possible to ensure the exception handler
    // is registered
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        (new StubsGenerator())->generateStubsIfNeeded($this->rootDir . '/.castor.stub.php');

        $this->initializeApplication();

        // Remove the try/catch when https://github.com/symfony/symfony/pull/50420 is released
        try {
            return parent::doRun($input, $output);
        } catch (\Throwable $e) {
            $this->renderThrowable($e, $output);

            return 1;
        }
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $this->initializeContext($input, $output);

        return parent::doRunCommand($command, $input, $output);
    }

    private function initializeApplication(): void
    {
        // Find all potential commands / context
        $functions = (new FunctionFinder())->findFunctions($this->rootDir);

        foreach ($functions as $function) {
            if ($function instanceof TaskDescriptor) {
                $this->add(new TaskCommand($function->taskAttribute, $function->function));
            } elseif ($function instanceof ContextBuilder) {
                $this->contextRegistry->addContextBuilder($function);
            }
        }

        $this->contextRegistry->setDefaultContextIfEmpty();

        $contextNames = $this->contextRegistry->getContextNames();
        if ($contextNames) {
            $this->getDefinition()->addOption(new InputOption(
                'context',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf('The context to use (%s)', implode('|', $contextNames)),
                $this->contextRegistry->getDefaultContextBuilder()->getName(),
                $contextNames,
            ));
        }
    }

    private function initializeContext(InputInterface $input, OutputInterface $output): void
    {
        $context = $this->createContext($input, $output);

        if ($context->verbosityLevel->isNotConfigured()) {
            $context = $context->withVerbosityLevel(VerbosityLevel::fromSymfonyOutput($output));
        }

        ContextRegistry::setInitialContext($context);
    }

    private function createContext(InputInterface $input, OutputInterface $output): Context
    {
        // occurs when running `castor -h`, or if no context is defined
        if (!$input->hasOption('context')) {
            return new Context();
        }

        $builder = $this
            ->contextRegistry
            ->getContextBuilder($input->getOption('context'))
        ;

        static $supportedParameterTypes = [
            SymfonyStyle::class,
            self::class,
            InputInterface::class,
            OutputInterface::class,
        ];

        $args = [];
        foreach ($builder->getParameters() as $parameter) {
            if (($type = $parameter->getType()) instanceof \ReflectionNamedType && \in_array($type->getName(), $supportedParameterTypes, true)) {
                $args[] = match ($type->getName()) {
                    SymfonyStyle::class => new SymfonyStyle($input, $output),
                    self::class => $this,
                    InputInterface::class => $input,
                    OutputInterface::class => $output,
                    default => throw new \LogicException(sprintf('Argument "%s" is not supported in context builder named "%s".', $parameter->getName(), $builder->getName())),
                };

                continue;
            }

            throw new \LogicException(sprintf('Argument "%s" is not supported in context builder named "%s".', $parameter->getName(), $builder->getName()));
        }

        return $builder->build(...$args);
    }
}
