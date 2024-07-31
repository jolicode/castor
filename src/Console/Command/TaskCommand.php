<?php

namespace Castor\Console\Command;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsCommandArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Console\Application;
use Castor\Console\Input\GetRawTokenTrait;
use Castor\ContextRegistry;
use Castor\Descriptor\TaskDescriptor;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\BeforeExecuteTaskEvent;
use Castor\Exception\FunctionConfigurationException;
use Castor\ExpressionLanguage;
use Castor\Helper\Slugger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** @internal */
#[Exclude]
class TaskCommand extends Command implements SignalableCommandInterface
{
    use GetRawTokenTrait;

    /**
     * @var array<string, string>
     */
    private array $argumentsMap = [];

    public function __construct(
        private readonly TaskDescriptor $taskDescriptor,
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ContextRegistry $contextRegistry,
        private readonly Slugger $slugger,
    ) {
        $this->setDescription($taskDescriptor->taskAttribute->description);
        $this->setAliases($taskDescriptor->taskAttribute->aliases);

        $taskName = $taskDescriptor->taskAttribute->name;
        if ($taskDescriptor->taskAttribute->namespace) {
            $taskName = $taskDescriptor->taskAttribute->namespace . ':' . $taskName;
        }

        $this->setProcessTitle(Application::NAME . ':' . $taskName);

        parent::__construct($taskName);
    }

    /**
     * @return array<int>
     */
    public function getSubscribedSignals(): array
    {
        return array_keys($this->taskDescriptor->taskAttribute->onSignals);
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        if (!\array_key_exists($signal, $this->taskDescriptor->taskAttribute->onSignals)) {
            return false;
        }

        return $this->taskDescriptor->taskAttribute->onSignals[$signal]($signal);
    }

    public function isEnabled(): bool
    {
        if (\is_bool($this->taskDescriptor->taskAttribute->enabled)) {
            return $this->taskDescriptor->taskAttribute->enabled;
        }

        return $this->expressionLanguage->evaluate($this->taskDescriptor->taskAttribute->enabled);
    }

    protected function configure(): void
    {
        if ($this->taskDescriptor->taskAttribute->ignoreValidationErrors) {
            $this->ignoreValidationErrors();
        }

        foreach ($this->taskDescriptor->function->getParameters() as $parameter) {
            if ($parameter->getAttributes(AsRawTokens::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null) {
                continue;
            }

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
                        $this->getSuggestedValues($taskArgumentAttribute),
                    );
                } elseif ($taskArgumentAttribute instanceof AsOption) {
                    if ('verbose' === $name) {
                        throw new FunctionConfigurationException('You cannot re-define a "verbose" option. But you can use "output()->isVerbose()" in your code instead.', $this->taskDescriptor->function);
                    }

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
                        $this->getSuggestedValues($taskArgumentAttribute),
                    );
                }
            } catch (LogicException $e) {
                throw new FunctionConfigurationException(\sprintf('The argument "%s" cannot be configured: "%s".', $parameter->getName(), $e->getMessage()), $this->taskDescriptor->function, $e);
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = [];

        foreach ($this->taskDescriptor->function->getParameters() as $parameter) {
            if ($parameter->getAttributes(AsRawTokens::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null) {
                $args[] = $this->getRawTokens($input);

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
            $function = $this->taskDescriptor->function->getClosure();
            if (!\is_callable($function)) {
                throw new \LogicException('The function is not a callable.');
            }

            $initialContext = $this->contextRegistry->getCurrentContext();

            try {
                if ($this->taskDescriptor->workingDirectory) {
                    $this->contextRegistry->setCurrentContext(
                        $initialContext->withWorkingDirectory($this->taskDescriptor->workingDirectory)
                    );
                }

                $this->eventDispatcher->dispatch(new BeforeExecuteTaskEvent($this));

                $result = $function(...$args);

                $this->eventDispatcher->dispatch(new AfterExecuteTaskEvent($this, $result));
            } finally {
                $this->contextRegistry->setCurrentContext($initialContext);
            }
        } catch (\Error $e) {
            $castorFunctions = array_filter(get_defined_functions()['user'], fn (string $functionName) => str_starts_with($functionName, 'castor\\'));
            $castorFunctionsWithoutNamespace = array_map(fn (string $functionName) => substr($functionName, \strlen('castor\\')), $castorFunctions);
            foreach ($castorFunctionsWithoutNamespace as $function) {
                if ("Call to undefined function {$function}()" === $e->getMessage()) {
                    throw new \LogicException(\sprintf('Call to undefined function %s(). Did you forget to import it? Try to add "use function Castor\%s;" in top of "%s" file.', $function, $function, $this->taskDescriptor->function->getFileName()), 0, $e);
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
        $name = $this->slugger->slug($name ?? $parameter->getName());

        $this->argumentsMap[$parameter->getName()] = $name;

        return $name;
    }

    private function getParameterName(\ReflectionParameter $parameter): string
    {
        return $this->argumentsMap[$parameter->getName()];
    }

    /**
     * @return array<string>|\Closure
     */
    private function getSuggestedValues(AsArgument|AsOption $attribute): array|\Closure
    {
        if ($attribute->suggestedValues && null !== $attribute->autocomplete) {
            throw new FunctionConfigurationException(\sprintf('You cannot define both "suggestedValues" and "autocomplete" option on parameter "%s".', $attribute->name), $this->taskDescriptor->function);
        }

        if (null === $attribute->autocomplete) {
            return $attribute->suggestedValues;
        }

        if (!\is_callable($attribute->autocomplete)) {
            throw new FunctionConfigurationException(\sprintf('The value provided in the "autocomplete" option on parameter "%s" is not callable.', $attribute->name), $this->taskDescriptor->function);
        }

        return \Closure::fromCallable($attribute->autocomplete);
    }
}
