<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
class AsListener
{
    public function __construct(
        public readonly string $event,
        public readonly int $priority = 0,
    ) {
    }
}
