<?php

namespace Castor\Remote\Exception;

class NotTrusted extends \RuntimeException
{
    public function __construct(
        public readonly string $url,
    ) {
        parent::__construct("The remote resource {$url} is not trusted.");
    }
}
