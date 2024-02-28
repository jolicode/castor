<?php

namespace Castor\Console\Command;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsCommandArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Console\Application;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\BeforeExecuteTaskEvent;
use Castor\EventDispatcher;
use Castor\ExpressionLanguage;
use Castor\SluggerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TaskCommand extends Command implements SignalableCommandInterface
{
    /**
     * @var array<string, string>
     */
    private array $argumentsMap = [];

    public function __construct(
        public readonly AsTask $taskAttribute,
        public readonly \ReflectionFunction $function,
        private readonly EventDispatcher $eventDispatcher,
        private readonly ExpressionLanguage $expressionLanguage,
    ) {
        $this->setDescription($taskAttribute->description);
        $this->setAliases($taskAttribute->aliases);

        $taskName = $taskAttribute->name;
        if ($taskAttribute->namespace) {
            $taskName = $taskAttribute->namespace . ':' . $taskName;
        }

        $this->setProcessTitle(Application::NAME . ':' . $taskName);

        parent::__construct($taskName);
    }

    /**
     * @return array<int>
     */
    public function getSubscribedSignals(): array
    {
        return array_keys($this->taskAttribute->onSignals);
    }

    public function handleSignal(int $signal): int|false
    {
        if (!\array_key_exists($signal, $this->taskAttribute->onSignals)) {
            return false;
        }

        return $this->taskAttribute->onSignals[$signal]($signal);
    }

    public function isEnabled(): bool
    {
        if (\is_bool($this->taskAttribute->enabled)) {
            return $this->taskAttribute->enabled;
        }

        return $this->expressionLanguage->evaluate($this->taskAttribute->enabled);
    }

    protected function configure(): void
    {
        foreach ($this->function->getParameters() as $parameter) {
            $taskArgumentAttribute = $parameter->getAttributes(AsCommandArgument::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

            if ($taskArgumentAttribute) {
                $taskArgumentAttribute = $taskArgumentAttribute->newInstance();
            } elseif ($parameter->isOptional()) {
                $taskArgumentAttribute = new AsOption();
            } else {
                $taskArgumentAttribute = new AsArgument();
            }

            $name = $this->setParameterName($parameter, $taskArgumentAttribute->name);

            try {
                if ($taskArgumentAttribute instanceof AsArgument) {
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
                        $taskArgumentAttribute->description,
                        $parameter->isOptional() ? $parameter->getDefaultValue() : null,
                        $taskArgumentAttribute->suggestedValues,
                    );
                } elseif ($taskArgumentAttribute instanceof AsOption) {
                    $mode = $taskArgumentAttribute->mode;
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
                        $taskArgumentAttribute->shortcut,
                        $mode,
                        $taskArgumentAttribute->description,
                        $defaultValue,
                        $taskArgumentAttribute->suggestedValues,
                    );
                }
            } catch (LogicException $e) {
                throw new \LogicException(sprintf('The argument "%s" for task "%s" cannot be configured: "%s".', $parameter->getName(), $this->getName(), $e->getMessage()));
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
            $function = $this->function->getClosure();
            if (!\is_callable($function)) {
                throw new \LogicException('The function is not a callable.');
            }

            $this->eventDispatcher->dispatch(new BeforeExecuteTaskEvent($this));

            $result = $function(...$args);

            $this->eventDispatcher->dispatch(new AfterExecuteTaskEvent($this, $result));
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
