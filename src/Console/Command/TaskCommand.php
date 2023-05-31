<?php

namespace Castor\Console\Command;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsCommandArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Context;
use Castor\GlobalHelper;
use Castor\SluggerHelper;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** @internal */
class TaskCommand extends Command
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
                    $this->addOption(
                        $name,
                        $commandArgumentAttribute->shortcut,
                        $mode = $commandArgumentAttribute->mode ?? InputOption::VALUE_OPTIONAL,
                        $commandArgumentAttribute->description,
                        $parameter->isOptional() ? $parameter->getDefaultValue() : null,
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
            if (($type = $parameter->getType()) instanceof \ReflectionNamedType && \in_array($type->getName(), self::SUPPORTED_PARAMETER_TYPES, true)) {
                $args[] = match ($type->getName()) {
                    Context::class => GlobalHelper::getInitialContext(),
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

        try {
            $result = $this->function->invoke(...$args);
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
