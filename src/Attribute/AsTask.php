<?php

namespace Castor\Attribute;

/**
 * Turns the function into a task, run with "castor <namespace>:<name>".
 *
 * The parameters of the function become the arguments of the task: a parameter
 * without default value is a required argument (an array one takes all the
 * remaining values), a parameter with a default value is an option
 * ("--my-param"), and a bool one is a flag. AsArgument and AsOption change this.
 *
 * The value returned by the function is the exit code of the task when it is
 * an int.
 */
#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsTask
{
    /**
     * @param string                                 $name                   Defaults to the function name, in kebab-case
     * @param ?string                                $namespace              Defaults to the PHP namespace of the function, in kebab-case and separated by ":". Pass "" for none
     * @param array<string>                          $aliases
     * @param array<int, callable(int): (int|false)> $onSignals              The callbacks to run when the task receives a signal, keyed by signal. They return the exit code to stop with, or false to keep running
     * @param string|bool                            $enabled                An expression may be given, like "var('production') == true || context().name == 'ci'"
     * @param bool                                   $ignoreValidationErrors Accepts unknown arguments and options
     * @param bool                                   $default                Runs this task when castor is called without a task name
     */
    public function __construct(
        public string $name = '',
        public ?string $namespace = null,
        public string $description = '',
        public array $aliases = [],
        public array $onSignals = [],
        public string|bool $enabled = true,
        public bool $ignoreValidationErrors = false,
        public bool $default = false,
    ) {
    }
}
