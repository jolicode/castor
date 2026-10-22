<?php

namespace Castor\Attribute;

use Symfony\Component\Console\Completion\CompletionInput;

/**
 * Makes the parameter of a task an option ("--my-param"), even when it has no
 * default value.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsOption extends AsCommandArgument
{
    /**
     * @param ?string                                                     $name         Defaults to the parameter name, in kebab-case
     * @param string|array<string>|null                                   $shortcut
     * @param ?int                                                        $mode         The InputOption::VALUE_* constants. Defaults to VALUE_NONE for a bool parameter, to VALUE_OPTIONAL otherwise
     * @param array<string>|callable(CompletionInput): array<string>|null $autocomplete The values suggested by the shell completion
     */
    public function __construct(
        ?string $name = null,
        public readonly string|array|null $shortcut = null,
        public readonly ?int $mode = null,
        public readonly string $description = '',
        public readonly mixed $autocomplete = null,
    ) {
        parent::__construct($name);
    }
}
