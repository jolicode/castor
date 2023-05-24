<?php

namespace Castor;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TaskAsCommand extends Command
{
    private const SUPPORTED_PARAMETER_TYPES = [
        Context::class,
        SymfonyStyle::class,
        Application::class,
        InputInterface::class,
        OutputInterface::class,
    ];

    /**
     * @var array<string, string>
     */
    private array $argumentsMap = [];

    public function __construct(
        AsTask $taskAttribute,
        private readonly \ReflectionFunction $function,
        private readonly ContextRegistry $contextRegistry,
    ) {
        $this->setDescription($taskAttribute->description);
        $this->setAliases($taskAttribute->aliases);

        $commandName = $taskAttribute->name;
        if ($taskAttribute->namespace) {
            $commandName = $taskAttribute->namespace . ':' . $commandName;
        }

        parent::__construct($commandName);
    }

    protected function configure(): void
    {
        foreach ($this->function->getParameters() as $parameter) {
            if (($type = $parameter->getType()) instanceof \ReflectionNamedType && \in_array($type->getName(), self::SUPPORTED_PARAMETER_TYPES, true)) {
                continue;
            }

            $argAttribute = $parameter->getAttributes(AsArgument::class)[0] ?? null;
            if ($argAttribute) {
                /** @var AsArgument */
                $argAttributeInstance = $argAttribute->newInstance();

                $name = $this->setParameterName($parameter, $argAttributeInstance->name);

                if ($parameter->isOptional()) {
                    $mode = InputArgument::OPTIONAL;
                } else {
                    $mode = InputArgument::REQUIRED;
                }
                if (($type = $parameter->getType()) instanceof \ReflectionNamedType && 'array' === $type->getName()) {
                    $mode |= InputArgument::IS_ARRAY;
                }

                try {
                    $this->addArgument(
                        $name,
                        $mode,
                        $argAttributeInstance->description,
                        $parameter->isOptional() ? $parameter->getDefaultValue() : null,
                        $argAttributeInstance->suggestedValues,
                    );
                } catch (LogicException $e) {
                    throw new \LogicException(sprintf('The argument "%s" for command "%s" cannot be configured: "%s".', $parameter->getName(), $this->getName(), $e->getMessage()));
                }

                continue;
            }
            $argAttribute = $parameter->getAttributes(AsOption::class)[0] ?? null;
            if ($argAttribute) {
                /** @var AsOption */
                $argAttributeInstance = $argAttribute->newInstance();

                $name = $this->setParameterName($parameter, $argAttributeInstance->name);

                try {
                    $this->addOption(
                        $name,
                        $argAttributeInstance->shortcut,
                        $mode = $argAttributeInstance->mode ?? InputOption::VALUE_OPTIONAL,
                        $argAttributeInstance->description,
                        $parameter->isOptional() ? $parameter->getDefaultValue() : null,
                        $argAttributeInstance->suggestedValues,
                    );
                } catch (LogicException $e) {
                    throw new \LogicException(sprintf('The argument "%s" for command "%s" cannot be configured: "%s".', $parameter->getName(), $this->getName(), $e->getMessage()));
                }

                continue;
            }

            $name = $this->setParameterName($parameter, null);
            if ($parameter->isOptional()) {
                $this->addOption($name, null, InputOption::VALUE_OPTIONAL, '', $parameter->getDefaultValue());
            } else {
                $this->addArgument($parameter->getName(), InputArgument::REQUIRED, '');
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = [];
        $contextName = $input->getOption('context');
        $contextBuilder = $this->contextRegistry->getContext($contextName);

        $context = $contextBuilder->build();
        ContextRegistry::setCurrentContext($context);

        foreach ($this->function->getParameters() as $parameter) {
            if (($type = $parameter->getType()) instanceof \ReflectionNamedType && \in_array($type->getName(), self::SUPPORTED_PARAMETER_TYPES, true)) {
                $args[] = match ($type->getName()) {
                    Context::class => $context,
                    SymfonyStyle::class => new SymfonyStyle($input, $output),
                    Application::class => $this->getApplication(),
                    InputInterface::class => $input,
                    OutputInterface::class => $output,
                };

                continue;
            }

            $name = $this->getParameterName($parameter);
            if ($input->hasArgument($name)) {
                $args[] = $input->getArgument($name);

                continue;
            }

            $args[] = $input->getOption($name);
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

    private function setParameterName(\ReflectionParameter $parameter, ?string $name): string
    {
        $name = SluggerHelper::slug($name ?? $parameter->getName());

        $this->argumentsMap[$parameter->getName()] = $name;

        return $name;
    }

    private function getParameterName(\ReflectionParameter $parameter): string
    {
        return $this->argumentsMap[$parameter->getName()];
    }
}
