<?php

namespace Castor\Console\Command;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsCommandArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\SluggerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
class TaskCommand extends Command
{
    /**
     * @var array<string, string>
     */
    private array $argumentsMap = [];

    public function __construct(
        AsTask $taskAttribute,
        private readonly \ReflectionFunction $function,
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
            $commandArgumentAttribute = $parameter->getAttributes(AsCommandArgument::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

            if ($commandArgumentAttribute) {
                $commandArgumentAttribute = $commandArgumentAttribute->newInstance();
            } elseif ($parameter->isOptional()) {
                $commandArgumentAttribute = new AsOption();
            } else {
                $commandArgumentAttribute = new AsArgument();
            }

            $name = $this->setParameterName($parameter, $commandArgumentAttribute->name);

            try {
                if ($commandArgumentAttribute instanceof AsArgument) {
                    if ($parameter->isOptional()) {
                        $mode = InputArgument::OPTIONAL;
                    } else {
                        $mode = InputArgument::REQUIRED;
                    }
                    if (($type = $parameter->getType()) instanceof \ReflectionNamedType && 'array' === $type->getName()) {
                        $mode |= InputArgument::IS_ARRAY;
                    }

                    $this->addArgument(
                        $name,
                        $mode,
                        $commandArgumentAttribute->description,
                        $parameter->isOptional() ? $parameter->getDefaultValue() : null,
                        $commandArgumentAttribute->suggestedValues,
                    );
                } elseif ($commandArgumentAttribute instanceof AsOption) {
                    $mode = $commandArgumentAttribute->mode;
                    $defaultValue = $parameter->isOptional() ? $parameter->getDefaultValue() : null;

                    if (!$mode) {
                        if (($type = $parameter->getType()) instanceof \ReflectionNamedType && 'bool' === $type->getName()) {
                            $mode |= InputOption::VALUE_NONE;
                            // Fix Symfony limitation: "Cannot set a default value when using InputOption::VALUE_NONE mode.
                            $defaultValue = null;
                        } else {
                            $mode = InputOption::VALUE_OPTIONAL;
                        }
                    }

                    $this->addOption(
                        $name,
                        $commandArgumentAttribute->shortcut,
                        $mode,
                        $commandArgumentAttribute->description,
                        $defaultValue,
                        $commandArgumentAttribute->suggestedValues,
                    );
                }
            } catch (LogicException $e) {
                throw new \LogicException(sprintf('The argument "%s" for command "%s" cannot be configured: "%s".', $parameter->getName(), $this->getName(), $e->getMessage()));
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = [];

        foreach ($this->function->getParameters() as $parameter) {
            $name = $this->getParameterName($parameter);
            if ($input->hasArgument($name)) {
                $args[] = $input->getArgument($name);

                continue;
            }

            $args[] = $input->getOption($name);
        }

        try {
            $function = $this->function->getName();
            if (!\is_callable($function)) {
                throw new \LogicException('The function is not a callable.');
            }
            $result = $function(...$args);
        } catch (\Error $e) {
            $castorFunctions = array_filter(get_defined_functions()['user'], fn (string $functionName) => str_starts_with($functionName, 'castor\\'));
            $castorFunctionsWithoutNamespace = array_map(fn (string $functionName) => substr($functionName, \strlen('castor\\')), $castorFunctions);
            foreach ($castorFunctionsWithoutNamespace as $function) {
                if ("Call to undefined function {$function}()" === $e->getMessage()) {
                    throw new \LogicException(sprintf('Call to undefined function %s(). Did you forget to import it? Try to add "use function Castor\%s;" in top of "%s" file.', $function, $function, $this->function->getFileName()));
                }
            }

            throw $e;
        }

        if (null === $result) {
            return Command::SUCCESS;
        }

        if (\is_int($result)) {
            return $result;
        }

        return Command::SUCCESS;
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
