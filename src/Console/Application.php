<?php

namespace Castor\Console;

use Castor\Console\Command\TaskCommand;
use Castor\ContextBuilder;
use Castor\ContextRegistry;
use Castor\FunctionFinder;
use Castor\TaskDescriptor;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
class Application extends SymfonyApplication
{
    public readonly ContextRegistry $contextRegistry;

    public function __construct(
        private readonly string $rootDir,
    ) {
        parent::__construct('castor', 'v0.1.0');

        $this->contextRegistry = new ContextRegistry();
    }

    // We do all the logic as late as possible to ensure the exception handler
    // is registered
    public function doRun(InputInterface $input, OutputInterface $output): int
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
        $context = $this
            ->contextRegistry
            ->getContextBuilder($input->getOption('context'))
            ->build()
        ;
        ContextRegistry::setInitialContext($context);

        return parent::doRunCommand($command, $input, $output);
    }
}
