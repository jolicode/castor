<?php

namespace Castor\Attribute;

use Symfony\Component\Console\Completion\CompletionInput;

/**
 * Makes the parameter of a task an argument, even when it has a default value:
 * the argument is then optional.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsArgument extends AsCommandArgument
{
    /**
     * @param ?string                                                     $name         Defaults to the parameter name, in kebab-case
     * @param array<string>|callable(CompletionInput): array<string>|null $autocomplete The values suggested by the shell completion
     */
    public function __construct(
        ?string $name = null,
        public readonly string $description = '',
        public readonly mixed $autocomplete = null,
    ) {
        parent::__construct($name);
    }
}
