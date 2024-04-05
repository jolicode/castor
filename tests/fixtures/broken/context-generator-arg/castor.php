<?php

use Castor\Attribute\AsContextGenerator;
use Castor\Context;

#[AsContextGenerator()]
function gen($a): iterable
{
    return new Context();
}
