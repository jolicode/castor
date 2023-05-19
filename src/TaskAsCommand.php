<?php

namespace Castor;

use Castor\Attribute\Arg;
use Castor\Attribute\Task;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TaskAsCommand extends Command
{
    public function __construct(
        Task $taskAttribute,
        private readonly \ReflectionFunction $function,
        private readonly ContextRegistry $contextRegistry,
    ) {
        $commandName = $taskAttribute->name;

        if ($taskAttribute->namespace) {
            $commandName = $taskAttribute->namespace . ':' . $commandName;
        }

        parent::__construct($commandName);

        $this->setDescription($taskAttribute->description);
    }

    protected function configure(): void
    {
        foreach ($this->function->getParameters() as $parameter) {
            $name = SluggerHelper::slug($parameter->getName());
            $shortcut = null;
            $description = '';
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            if (Context::class === $type->getName()) {
                continue;
            }

            if (SymfonyStyle::class === $type->getName()) {
                continue;
            }

            $argAttribute = $parameter->getAttributes(Arg::class);

            if (0 !== \count($argAttribute)) {
                $argAttributeInstance = $argAttribute[0]->newInstance();
                $name = $argAttributeInstance->name ?: $name;
                $description = $argAttributeInstance->description ?: $description;
                $shortcut = $argAttributeInstance->shortcut ?: $shortcut;
            }

            if ($parameter->isOptional()) {
                $this->addOption($name, $shortcut, InputOption::VALUE_OPTIONAL, $description, $parameter->getDefaultValue());
            } else {
                $this->addArgument($parameter->getName(), InputArgument::REQUIRED, $description);
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = [];
        $contextName = $input->getOption('context');
        $contextBuilder = $this->contextRegistry->getContext($contextName);

        $context = $contextBuilder->build();
        ContextRegistry::$currentContext = $context;

        foreach ($this->function->getParameters() as $parameter) {
            $name = SluggerHelper::slug($parameter->getName());
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            if (Context::class === $type->getName()) {
                $args[] = $context;

                continue;
            }

            if (SymfonyStyle::class === $type->getName()) {
                $args[] = new SymfonyStyle($input, $output);

                continue;
            }

            $argAttribute = $parameter->getAttributes(Arg::class);

            if (0 !== \count($argAttribute)) {
                $argAttributeInstance = $argAttribute[0]->newInstance();
                $name = $argAttributeInstance->name ?: $name;
            }

            if ($parameter->isOptional()) {
                if ($input->hasOption($name)) {
                    $args[] = $input->getOption($name);
                }
            } else {
                $args[] = $input->getArgument($name);
            }
        }

        $result = $this->function->invoke(...$args);

        if (null === $result) {
            return 0;
        }

        if (\is_int($result)) {
            return $result;
        }

        return 0;
    }
}
