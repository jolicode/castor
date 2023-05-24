<?php

namespace Castor\Console;

use Castor\Console\Command\TaskCommand;
use Castor\ContextBuilder;
use Castor\ContextRegistry;
use Castor\FunctionFinder;
use Castor\TaskDescriptor;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends SymfonyApplication
{
    public function __construct(
        private readonly string $rootDir,
    ) {
        parent::__construct('castor');
    }

    // We do all the logic as late as possible to exception handler is
    // registered
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        // Find all potential commands / context
        $functions = (new FunctionFinder())->findFunctions($this->rootDir);

        $defaultContext = null;
        $contextRegistry = new ContextRegistry();

        /** @var TaskDescriptor[] $taskDescriptors */
        $taskDescriptors = [];

        foreach ($functions as $function) {
            if ($function instanceof TaskDescriptor) {
                $taskDescriptors[] = $function;

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

                $contextRegistry->addContextBuilder($function->getName(), $function);
            }
        }

        $defaultContext ??= ContextBuilder::createDefault();

        $contextRegistry->addContextBuilder('default', $defaultContext);

        foreach ($taskDescriptors as $taskDescriptor) {
            $this->add(new TaskCommand($taskDescriptor->taskAttribute, $taskDescriptor->function, $contextRegistry));
        }

        $contextNames = implode('|', $contextRegistry->getContextNames());

        $this->getDefinition()->addOption(new InputOption(
            'context',
            null,
            InputOption::VALUE_REQUIRED,
            "The context to use ({$contextNames})",
            'default',
            $contextRegistry->getContextNames(),
        ));

        // Remove the try/catch when https://github.com/symfony/symfony/pull/50420 is released
        try {
            return parent::doRun($input, $output);
        } catch (\Throwable $e) {
            $this->renderThrowable($e, $output);

            return 1;
        }
    }
}
