<?php

namespace Castor;

use Castor\Attribute\AsContextGenerator;

/** @internal */
class ContextGeneratorDescriptor
{
    /**
     * @param array<\Closure(): Context> $generators
     */
    public function __construct(
        public readonly AsContextGenerator $contextAttribute,
        public readonly \ReflectionFunction $function,
        public readonly array $generators,
    ) {
    }
}
