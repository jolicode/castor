<?php

use Castor\Attribute\AsContextGenerator;
use Castor\Context;

#[AsContextGenerator()]
function gen(): iterable
{
    yield 'foo' => static fn ($a) => new Context();
}
