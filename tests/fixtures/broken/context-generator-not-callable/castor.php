<?php

use Castor\Attribute\AsContextGenerator;

#[AsContextGenerator()]
function gen(): iterable
{
    yield 'foo' => 'not a callable';
}
