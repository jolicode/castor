<?php

namespace Castor;

use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

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
        return new class('castor') extends Application {
            // We register the task as late as possible to ensure the
            // exception listener is registered
            public function doRun(InputInterface $input, OutputInterface $output)
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

                foreach ($taskDescriptors as $taskDescriptor) {
                    $this->add(new TaskAsCommand($taskDescriptor->taskAttribute, $taskDescriptor->function, $contextRegistry));
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
        };
    }
}
