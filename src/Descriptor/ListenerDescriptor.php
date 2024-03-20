<?php

namespace Castor\Descriptor;

use Castor\Attribute\AsListener;

class ListenerDescriptor
{
    public function __construct(
        public readonly AsListener $asListener,
        public readonly \ReflectionFunction $reflectionFunction,
    ) {
    }
}
