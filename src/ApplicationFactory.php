<?php

namespace Castor;

use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;

class ApplicationFactory
{
    public static function run(): void
    {
        $application = self::create();

        $input = new ArgvInput();
        $output = new ConsoleOutput();

        $logger = new Logger('castor', [
            new ConsoleHandler($output),
        ]);

        ContextRegistry::setLogger($logger);

        $application->run($input, $output);
    }

    public static function create(): Application
    {
        $finder = new FunctionFinder();
        $path = PathHelper::getRoot();

        // Find all potential commands / context
        $functions = $finder->findFunctions($path);

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

        $contextRegistry->addContextBuilder('default', $defaultContext ?? ContextBuilder::createDefault());

        $application = new Application('castor');
        $contextNames = implode('|', $contextRegistry->getContextNames());

        $inputDefinition = $application->getDefinition();
        $inputDefinition->addOption(new InputOption(
            'context',
            null,
            InputOption::VALUE_REQUIRED,
            "The context to use ({$contextNames})",
            'default',
            $contextRegistry->getContextNames(),
        ));

        foreach ($taskDescriptors as $taskDescriptor) {
            $application->add(new TaskAsCommand($taskDescriptor->taskAttribute, $taskDescriptor->function, $contextRegistry));
        }

        return $application;
    }
}
