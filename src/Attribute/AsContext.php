<?php

namespace Castor\Attribute;

/**
 * Turns the function into a context: it returns the Context of the tasks
 * when it is selected with "castor --context=<name>", the CASTOR_CONTEXT
 * environment variable, or a ".castor.context" file.
 */
#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsContext
{
    /**
     * @param string $name    Defaults to the function name in kebab-case, or to "default" for the default context
     * @param bool   $default Uses this context when none is selected
     */
    public function __construct(
        public string $name = '',
        public bool $default = false,
    ) {
    }
}
