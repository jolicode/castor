<?php

namespace Castor;

class Context extends \ArrayObject {
    public string $currentDirectory;

    public function __construct(
        public array $data = [],
        public array $environment = [],
    ) {
        parent::__construct($data, \ArrayObject::ARRAY_AS_PROPS);

        $this->currentDirectory = getcwd();
    }
}
