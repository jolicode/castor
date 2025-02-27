<?php

namespace Castor\Console\Command;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsCommandArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsPathArgument;
use Castor\Attribute\AsPathOption;
use Castor\Attribute\AsRawTokens;
use Castor\Console\Application;
use Castor\Console\Input\GetRawTokenTrait;
use Castor\ContextRegistry;
use Castor\Descriptor\TaskDescriptor;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\BeforeExecuteTaskEvent;
use Castor\Exception\FunctionConfigurationException;
use Castor\ExpressionLanguage;
use Castor\Helper\PathHelper;
use Castor\Helper\Slugger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

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
        private readonly Filesystem $fs,
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

    /**
     * @template T of object
     *
     * Returns an array of function attributes.
     *
     * @param class-string<T>|null $name  Name of an attribute class
     * @param int                  $flags сriteria by which the attribute is searched
     *
     * @return \ReflectionAttribute<T>[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        return $this->taskDescriptor->function->getAttributes($name, $flags);
    }

    protected function configure(): void
    {
        if ($this->taskDescriptor->taskAttribute->ignoreValidationErrors) {
            $this->ignoreValidationErrors();
        }

        foreach ($this->taskDescriptor->function->getParameters() as $parameter) {
            if ($parameter->getAttributes(AsRawTokens::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null) {
                $this->ignoreValidationErrors();

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
            // @phpstan-ignore function.alreadyNarrowedType
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
            $castorFunctions = array_filter(get_defined_functions()['user'], fn (string $functionName): bool => str_starts_with($functionName, 'castor\\'));
            $castorFunctionsWithoutNamespace = array_map(fn (string $functionName): string => substr($functionName, \strlen('castor\\')), $castorFunctions);
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
        if ($attribute instanceof AsPathArgument || $attribute instanceof AsPathOption) {
            return \Closure::fromCallable(function (CompletionInput $input): array {
                $value = $input->getCompletionValue();

                // If no value is typed, we suggest items in the root directory
                if (!$value || '.' === $value) {
                    return $this->getPathSuggestions(PathHelper::getRoot(), '');
                }

                $path = $value;

                // If the currently typed value is not an absolute path, we will suggest items in the root directory
                if (!$this->fs->isAbsolutePath($value)) {
                    $path = PathHelper::getRoot() . \DIRECTORY_SEPARATOR . $value;
                }

                // If the typed value exists and is a directory, we will suggest items in that directory
                if ($this->fs->exists($path) && is_dir($path) && is_readable($path) && !str_ends_with($value, '.')) {
                    return $this->getPathSuggestions($path, rtrim($value, '/\\') . \DIRECTORY_SEPARATOR);
                }

                $parentDir = \dirname($path);

                // If the "parent directory" of the currently typed value does not exist, there is nothing to suggest
                if (!$this->fs->exists($parentDir) || !is_dir($parentDir) || !is_readable($parentDir)) {
                    return [];
                }

                // If the user typed "foo/b":
                // - $value will be "foo/b"
                // - $path wil be "/path/to/castor/project/foo/b"
                // - $parentDir will be "/path/to/castor/project/foo"
                //
                // So we want to:
                // - suggest items in the $parentDir directory
                // - but items should be relative to typed value and start with "foo/"
                $baseValue = mb_substr($value, 0, 1 + mb_strlen($value) - mb_strlen(str_replace($parentDir, '', $path)));

                return $this->getPathSuggestions($parentDir, $baseValue);
            });
        }

        if ($attribute->suggestedValues && !\in_array('_complete', $_SERVER['argv'], true)) {
            // Only trigger deprecation when not in completion mode
            trigger_deprecation('jolicode/castor', '0.18', \sprintf('The "suggestedValues" property of attribute "%s" is deprecated, use "autocomplete" property instead.', self::class));
        }

        if ($attribute->suggestedValues && null !== $attribute->autocomplete) {
            throw new FunctionConfigurationException(\sprintf('You cannot define both "suggestedValues" and "autocomplete" option on parameter "%s".', $attribute->name), $this->taskDescriptor->function);
        }

        if (null === $attribute->autocomplete) {
            return $attribute->suggestedValues;
        }

        if (\is_array($attribute->autocomplete)) {
            return $attribute->autocomplete;
        }

        // @phpstan-ignore booleanNot.alwaysFalse
        if (!\is_callable($attribute->autocomplete)) {
            throw new FunctionConfigurationException(\sprintf('The value provided in the "autocomplete" option on parameter "%s" is not callable.', $attribute->name), $this->taskDescriptor->function);
        }

        return \Closure::fromCallable($attribute->autocomplete);
    }

    /**
     * @return array<string>
     */
    private function getPathSuggestions(string $path, string $baseValue): array
    {
        $items = scandir($path);

        if (false === $items) {
            return [];
        }

        return array_map(
            fn (string $item): string => $baseValue . $item . (is_dir($baseValue . $item) ? \DIRECTORY_SEPARATOR : ''),
            array_filter(
                $items,
                fn (string $suggestion): bool => '.' !== $suggestion && '..' !== $suggestion,
            ),
        );
    }
}
