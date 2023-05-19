<?php

namespace Castor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

class ApplicationFactory
{
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

                $contextRegistry->addContext($function->getName(), $function);
            }
        }

        $defaultContext ??= ContextBuilder::createDefault();

        $contextRegistry->addContext('default', $defaultContext);

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
