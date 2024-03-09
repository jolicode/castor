<?php

use Castor\Attribute\AsContextGenerator;
use Castor\Context;

#[AsContextGenerator()]
function gen(): iterable
{
    yield 'foo' => fn ($a) => new Context();
}
