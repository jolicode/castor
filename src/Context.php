<?php

namespace Castor;

use ArrayObject;

/**
 * @template TValue
 *
 * @template-extends ArrayObject<(int|string), TValue>
 */
class Context extends \ArrayObject
{
    public string $currentDirectory;

    /**
     * @param array<(int|string), TValue> $array The input parameter accepts an array or an Object
     * @param array<string, string> $environment a list of environment variables to add to the command
     */
    public function __construct(
        public array $array = [],
        public array $environment = [],
    ) {
        parent::__construct($array, \ArrayObject::ARRAY_AS_PROPS);

        $this->currentDirectory = PathHelper::getCwd();
    }
}
