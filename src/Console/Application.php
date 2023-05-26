<?php

namespace Castor\Console;

use Castor\Console\Command\TaskCommand;
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

        $defaultContext = null;

        foreach ($functions as $function) {
            if ($function instanceof TaskDescriptor) {
                $this->add(new TaskCommand($function->taskAttribute, $function->function));

                continue;
            }

            if ($function instanceof ContextBuilder) {
                if ($function->isDefault()) {
                    if (null !== $defaultContext) {
                        throw new \LogicException('Only one default context is allowed.');
                    }

                    $defaultContext = $function;

                    continue;
                }

                $this->contextRegistry->addContextBuilder($function->getName(), $function);
            }
        }

        $this->contextRegistry->addContextBuilder('default', $defaultContext ?? ContextBuilder::createDefault());

        $contextNames = implode('|', $this->contextRegistry->getContextNames());
        $this->getDefinition()->addOption(new InputOption(
            'context',
            null,
            InputOption::VALUE_REQUIRED,
            "The context to use ({$contextNames})",
            'default',
            $this->contextRegistry->getContextNames(),
        ));
    }

    private function initializeContext(InputInterface $input, OutputInterface $output): void
    {
        // occurs when running `castor -h`
        if (!$input->hasOption('context')) {
            return;
        }

        $builder = $this
            ->contextRegistry
            ->getContextBuilder($input->getOption('context'))
        ;

        $args = [];
        $verbosityLevelSet = false;
        foreach ($builder->getParameters() as $parameters) {
            if ('verbosityLevel' === $parameters->getName() && VerbosityLevel::class === $parameters->getType()->getName()) {
                $args[] = VerbosityLevel::fromSymfonyOutput($output);
                $verbosityLevelSet = true;

                continue;
            }

            throw new \LogicException(sprintf('Only the "int $verbosityLevel" parameter is supported in context builder named "%s".', $builder->getName()));
        }

        $context = $builder->build(...$args);

        if (!$verbosityLevelSet) {
            $context = $context->withVerbosityLevel(VerbosityLevel::fromSymfonyOutput($output));
        }

        ContextRegistry::setInitialContext($context);
    }
}
