<?php

namespace Castor;

use Castor\Attribute\Task;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TaskAsCommand extends Command
{
    public function __construct(Task $taskAttribute, private \ReflectionFunction $function, private ContextRegistry $contextRegistry)
    {
        $commandName = $taskAttribute->name;

        if ($taskAttribute->namespace !== null && $taskAttribute->namespace !== '') {
            $commandName = $taskAttribute->namespace . ':' . $commandName;
        }

        parent::__construct($commandName);

        $this->setDescription($taskAttribute->description);
    }

    protected function configure(): void
    {

        foreach ($this->function->getParameters() as $parameter) {
            $name = strtolower($parameter->getName());
            $type = $parameter->getType();

            if ($type !== null && $type->getName() === Context::class) {
                continue;
            }

            if ($type !== null && $type->getName() === SymfonyStyle::class) {
                continue;
            }

            if ($parameter->isOptional()) {
                $this->addOption($name, null, InputOption::VALUE_OPTIONAL, '', $parameter->getDefaultValue());
            } else {
                $this->addArgument($parameter->getName(), InputArgument::REQUIRED);
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = [];
        $contextName = $input->getOption('context');
        $contextBuilder = $this->contextRegistry->getContext($contextName);

        if ($contextBuilder === null) {
            throw new \Exception("Context $contextName does not exist");
        }

        global $context;
        $context = $contextBuilder->build();

        foreach ($this->function->getParameters() as $parameter) {
            $name = strtolower($parameter->getName());
            $type = $parameter->getType();

            if ($type !== null && $type->getName() === Context::class) {
                $args[] = $context;
                continue;
            }

            if ($type !== null && $type->getName() === SymfonyStyle::class) {
                $args[] = new SymfonyStyle($input, $output);
                continue;
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

        if ($result === null) {
            return 0;
        }

        if (is_int($result)) {
            return $result;
        }

        return 0;
    }
}